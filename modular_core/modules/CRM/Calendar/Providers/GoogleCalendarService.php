<?php
/**
 * CRM/Calendar/Providers/GoogleCalendarService.php
 *
 * Google Calendar API integration via OAuth 2.0.
 *
 * Requirements: 16.2, 16.3, 16.4
 */

declare(strict_types=1);

namespace CRM\Calendar\Providers;

class GoogleCalendarService
{
    private const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const CALENDAR_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    private const SCOPES = [
        'https://www.googleapis.com/auth/calendar.events',
        'openid',
        'email',
    ];

    // -------------------------------------------------------------------------
    // OAuth
    // -------------------------------------------------------------------------

    /**
     * Build Google OAuth authorization URL.
     *
     * @param  int    $userId   Used as state parameter
     * @return string
     */
    public function getAuthUrl(int $userId): string
    {
        $appUrl      = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost';
        $clientId    = $_ENV['GOOGLE_CALENDAR_CLIENT_ID'] ?? getenv('GOOGLE_CALENDAR_CLIENT_ID') ?: '';
        $redirectUri = rtrim($appUrl, '/') . '/api/v1/crm/calendar/callback/google';

        $params = [
            'client_id'             => $clientId,
            'redirect_uri'          => $redirectUri,
            'response_type'         => 'code',
            'scope'                 => implode(' ', self::SCOPES),
            'access_type'           => 'offline',
            'prompt'                => 'consent',
            'state'                 => (string) $userId,
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @param  string $code
     * @return array  { access_token, refresh_token, expires_in, token_type }
     * @throws \RuntimeException on failure
     */
    public function exchangeCode(string $code): array
    {
        $appUrl       = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost';
        $clientId     = $_ENV['GOOGLE_CALENDAR_CLIENT_ID']     ?? getenv('GOOGLE_CALENDAR_CLIENT_ID')     ?: '';
        $clientSecret = $_ENV['GOOGLE_CALENDAR_CLIENT_SECRET'] ?? getenv('GOOGLE_CALENDAR_CLIENT_SECRET') ?: '';
        $redirectUri  = rtrim($appUrl, '/') . '/api/v1/crm/calendar/callback/google';

        $response = $this->httpPost(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        if (empty($response['access_token'])) {
            throw new \RuntimeException('Google token exchange failed: ' . ($response['error_description'] ?? 'unknown error'));
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
        $clientId     = $_ENV['GOOGLE_CALENDAR_CLIENT_ID']     ?? getenv('GOOGLE_CALENDAR_CLIENT_ID')     ?: '';
        $clientSecret = $_ENV['GOOGLE_CALENDAR_CLIENT_SECRET'] ?? getenv('GOOGLE_CALENDAR_CLIENT_SECRET') ?: '';

        $response = $this->httpPost(self::TOKEN_URL, [
            'refresh_token' => $refreshToken,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'grant_type'    => 'refresh_token',
        ]);

        if (empty($response['access_token'])) {
            throw new \RuntimeException('Google token refresh failed: ' . ($response['error_description'] ?? 'unknown error'));
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // Calendar event CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a Google Calendar event from an activity.
     *
     * @param  array  $tokens    { access_token }
     * @param  array  $activity  Activity row
     * @return string            External event ID
     * @throws \RuntimeException on failure
     */
    public function createEvent(array $tokens, array $activity): string
    {
        $event    = $this->activityToGoogleEvent($activity);
        $response = $this->httpRequest('POST', self::CALENDAR_URL, $tokens['access_token'], $event);

        if (empty($response['id'])) {
            throw new \RuntimeException('Google Calendar createEvent failed: ' . json_encode($response));
        }

        return $response['id'];
    }

    /**
     * Update an existing Google Calendar event.
     *
     * @param  array  $tokens
     * @param  string $eventId
     * @param  array  $activity
     * @throws \RuntimeException on failure
     */
    public function updateEvent(array $tokens, string $eventId, array $activity): void
    {
        $url   = self::CALENDAR_URL . '/' . urlencode($eventId);
        $event = $this->activityToGoogleEvent($activity);
        $this->httpRequest('PUT', $url, $tokens['access_token'], $event);
    }

    /**
     * Delete a Google Calendar event.
     *
     * @param  array  $tokens
     * @param  string $eventId
     * @throws \RuntimeException on failure
     */
    public function deleteEvent(array $tokens, string $eventId): void
    {
        $url = self::CALENDAR_URL . '/' . urlencode($eventId);
        $this->httpRequest('DELETE', $url, $tokens['access_token']);
    }

    /**
     * List recent changes using incremental sync.
     *
     * @param  array  $tokens
     * @param  string $syncToken  Previous sync token (empty for full sync)
     * @return array  { events: [], nextSyncToken: string }
     */
    public function listRecentChanges(array $tokens, string $syncToken): array
    {
        $params = ['maxResults' => 250];
        if ($syncToken !== '') {
            $params['syncToken'] = $syncToken;
        } else {
            // Initial sync: fetch events updated in the last 7 days
            $params['updatedMin'] = (new \DateTimeImmutable('-7 days', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);
        }

        $url      = self::CALENDAR_URL . '?' . http_build_query($params);
        $response = $this->httpRequest('GET', $url, $tokens['access_token']);

        return [
            'events'        => $response['items'] ?? [],
            'nextSyncToken' => $response['nextSyncToken'] ?? '',
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Convert an activity row to a Google Calendar event payload.
     */
    private function activityToGoogleEvent(array $activity): array
    {
        $start = $activity['activity_date'] ?? $activity['start_at'] ?? date('Y-m-d\TH:i:s\Z');
        $durationMinutes = (int) ($activity['duration_minutes'] ?? 60);

        $startDt = new \DateTimeImmutable($start, new \DateTimeZone('UTC'));
        $endDt   = $startDt->modify("+{$durationMinutes} minutes");

        return [
            'summary'     => $activity['subject'] ?? 'Meeting',
            'description' => $activity['body'] ?? '',
            'start'       => ['dateTime' => $startDt->format(\DateTimeInterface::ATOM), 'timeZone' => 'UTC'],
            'end'         => ['dateTime' => $endDt->format(\DateTimeInterface::ATOM),   'timeZone' => 'UTC'],
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
     * Perform an authenticated HTTP request to the Calendar API.
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
