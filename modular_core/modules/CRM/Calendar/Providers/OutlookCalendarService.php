<?php
/**
 * CRM/Calendar/Providers/OutlookCalendarService.php
 *
 * Microsoft Outlook Calendar integration via OAuth 2.0 and Microsoft Graph API.
 *
 * Requirements: 16.2, 16.3, 16.4
 */

declare(strict_types=1);

namespace CRM\Calendar\Providers;

class OutlookCalendarService
{
    private const AUTH_URL   = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    private const TOKEN_URL  = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    private const EVENTS_URL = 'https://graph.microsoft.com/v1.0/me/events';

    private const SCOPES = [
        'Calendars.ReadWrite',
        'offline_access',
        'openid',
        'email',
    ];

    // -------------------------------------------------------------------------
    // OAuth
    // -------------------------------------------------------------------------

    /**
     * Build Microsoft OAuth authorization URL.
     *
     * @param  int    $userId  Used as state parameter
     * @return string
     */
    public function getAuthUrl(int $userId): string
    {
        $appUrl      = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost';
        $clientId    = $_ENV['OUTLOOK_CALENDAR_CLIENT_ID'] ?? getenv('OUTLOOK_CALENDAR_CLIENT_ID') ?: '';
        $redirectUri = rtrim($appUrl, '/') . '/api/v1/crm/calendar/callback/outlook';

        $params = [
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => implode(' ', self::SCOPES),
            'state'         => (string) $userId,
            'response_mode' => 'query',
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @param  string $code
     * @return array  { access_token, refresh_token, expires_in }
     * @throws \RuntimeException on failure
     */
    public function exchangeCode(string $code): array
    {
        $appUrl       = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost';
        $clientId     = $_ENV['OUTLOOK_CALENDAR_CLIENT_ID']     ?? getenv('OUTLOOK_CALENDAR_CLIENT_ID')     ?: '';
        $clientSecret = $_ENV['OUTLOOK_CALENDAR_CLIENT_SECRET'] ?? getenv('OUTLOOK_CALENDAR_CLIENT_SECRET') ?: '';
        $redirectUri  = rtrim($appUrl, '/') . '/api/v1/crm/calendar/callback/outlook';

        $response = $this->httpPost(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
            'scope'         => implode(' ', self::SCOPES),
        ]);

        if (empty($response['access_token'])) {
            throw new \RuntimeException('Outlook token exchange failed: ' . ($response['error_description'] ?? 'unknown error'));
        }

        return $response;
    }

    /**
     * Refresh an expired access token.
     *
     * @param  string $refreshToken
     * @return array  { access_token, expires_in }
     * @throws \RuntimeException on failure
     */
    public function refreshToken(string $refreshToken): array
    {
        $clientId     = $_ENV['OUTLOOK_CALENDAR_CLIENT_ID']     ?? getenv('OUTLOOK_CALENDAR_CLIENT_ID')     ?: '';
        $clientSecret = $_ENV['OUTLOOK_CALENDAR_CLIENT_SECRET'] ?? getenv('OUTLOOK_CALENDAR_CLIENT_SECRET') ?: '';

        $response = $this->httpPost(self::TOKEN_URL, [
            'refresh_token' => $refreshToken,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'grant_type'    => 'refresh_token',
            'scope'         => implode(' ', self::SCOPES),
        ]);

        if (empty($response['access_token'])) {
            throw new \RuntimeException('Outlook token refresh failed: ' . ($response['error_description'] ?? 'unknown error'));
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // Calendar event CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a Microsoft Graph calendar event from an activity.
     *
     * @param  array  $tokens    { access_token }
     * @param  array  $activity  Activity row
     * @return string            External event ID
     * @throws \RuntimeException on failure
     */
    public function createEvent(array $tokens, array $activity): string
    {
        $event    = $this->activityToGraphEvent($activity);
        $response = $this->httpRequest('POST', self::EVENTS_URL, $tokens['access_token'], $event);

        if (empty($response['id'])) {
            throw new \RuntimeException('Outlook createEvent failed: ' . json_encode($response));
        }

        return $response['id'];
    }

    /**
     * Update an existing Microsoft Graph calendar event.
     *
     * @param  array  $tokens
     * @param  string $eventId
     * @param  array  $activity
     * @throws \RuntimeException on failure
     */
    public function updateEvent(array $tokens, string $eventId, array $activity): void
    {
        $url   = self::EVENTS_URL . '/' . urlencode($eventId);
        $event = $this->activityToGraphEvent($activity);
        $this->httpRequest('PATCH', $url, $tokens['access_token'], $event);
    }

    /**
     * Delete a Microsoft Graph calendar event.
     *
     * @param  array  $tokens
     * @param  string $eventId
     * @throws \RuntimeException on failure
     */
    public function deleteEvent(array $tokens, string $eventId): void
    {
        $url = self::EVENTS_URL . '/' . urlencode($eventId);
        $this->httpRequest('DELETE', $url, $tokens['access_token']);
    }

    /**
     * List recent changes using Microsoft Graph delta query.
     *
     * @param  array  $tokens
     * @param  string $deltaLink  Previous delta link (empty for initial sync)
     * @return array  { events: [], nextDeltaLink: string }
     */
    public function listRecentChanges(array $tokens, string $deltaLink): array
    {
        if ($deltaLink !== '') {
            $url = $deltaLink;
        } else {
            // Initial sync: fetch events from the last 7 days
            $startDt = (new \DateTimeImmutable('-7 days', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);
            $endDt   = (new \DateTimeImmutable('+30 days', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);
            $url     = self::EVENTS_URL . '/delta?' . http_build_query([
                'startDateTime' => $startDt,
                'endDateTime'   => $endDt,
            ]);
        }

        $response = $this->httpRequest('GET', $url, $tokens['access_token']);

        return [
            'events'        => $response['value'] ?? [],
            'nextDeltaLink' => $response['@odata.deltaLink'] ?? '',
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Convert an activity row to a Microsoft Graph event payload.
     */
    private function activityToGraphEvent(array $activity): array
    {
        $start = $activity['activity_date'] ?? $activity['start_at'] ?? date('Y-m-d\TH:i:s\Z');
        $durationMinutes = (int) ($activity['duration_minutes'] ?? 60);

        $startDt = new \DateTimeImmutable($start, new \DateTimeZone('UTC'));
        $endDt   = $startDt->modify("+{$durationMinutes} minutes");

        return [
            'subject' => $activity['subject'] ?? 'Meeting',
            'body'    => [
                'contentType' => 'text',
                'content'     => $activity['body'] ?? '',
            ],
            'start' => [
                'dateTime' => $startDt->format('Y-m-d\TH:i:s'),
                'timeZone' => 'UTC',
            ],
            'end' => [
                'dateTime' => $endDt->format('Y-m-d\TH:i:s'),
                'timeZone' => 'UTC',
            ],
        ];
    }

    /**
     * Perform an HTTP POST with form-encoded body (for token endpoints).
     */
    private function httpPost(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $body = curl_exec($ch);
        curl_close($ch);

        return json_decode((string) $body, true) ?? [];
    }

    /**
     * Perform an authenticated HTTP request to the Graph API.
     */
    private function httpRequest(string $method, string $url, string $accessToken, ?array $body = null): array
    {
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
        ];

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }

        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false || $response === '') {
            return [];
        }

        return json_decode((string) $response, true) ?? [];
    }
}
