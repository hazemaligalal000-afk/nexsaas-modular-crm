<?php
/**
 * CRM/Email/EmailSyncService.php
 *
 * Syncs emails from connected Gmail / Microsoft 365 mailboxes into the Inbox,
 * links them to matching Contacts, sends outbound emails with tracking injection.
 *
 * Requirements: 13.2, 13.3, 13.4
 */

declare(strict_types=1);

namespace CRM\Email;

use Core\BaseService;
use CRM\Inbox\InboxService;

class EmailSyncService extends BaseService
{
    private MailboxConnectionService $connectionService;

    public function __construct($db, MailboxConnectionService $connectionService)
    {
        parent::__construct($db);
        $this->connectionService = $connectionService;
    }

    // -------------------------------------------------------------------------
    // Sync — Requirement 13.2
    // -------------------------------------------------------------------------

    /**
     * Fetch new emails for a mailbox since last_sync_at and create inbox records.
     *
     * For each email:
     *  - Creates an inbox_conversation + inbox_message
     *  - Links to matching Contact via InboxService::autoLinkConversation logic
     *  - Updates last_sync_at on success; sets sync_status=error on failure
     *
     * @param  int $mailboxId
     * @return array  ['synced' => int, 'errors' => array]
     * @throws \RuntimeException if mailbox not found
     */
    public function syncMailbox(int $mailboxId): array
    {
        $rs = $this->db->Execute(
            'SELECT * FROM connected_mailboxes WHERE id = ? AND deleted_at IS NULL',
            [$mailboxId]
        );

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException("Mailbox {$mailboxId} not found.");
        }

        $mailbox     = $rs->fields;
        $tenantId    = $mailbox['tenant_id'];
        $companyCode = $mailbox['company_code'];
        $provider    = $mailbox['provider'];
        $lastSyncAt  = $mailbox['last_sync_at'];

        // Refresh token if expired
        $expiresAt = strtotime($mailbox['token_expires_at'] ?? '');
        if ($expiresAt && $expiresAt < time() + 60) {
            try {
                $this->connectionService->refreshToken($mailboxId);
                // Reload after refresh
                $rs2     = $this->db->Execute('SELECT * FROM connected_mailboxes WHERE id = ?', [$mailboxId]);
                $mailbox = (!$rs2->EOF) ? $rs2->fields : $mailbox;
            } catch (\Exception $e) {
                $this->markSyncError($mailboxId, 'Token refresh failed: ' . $e->getMessage());
                return ['synced' => 0, 'errors' => [$e->getMessage()]];
            }
        }

        $accessToken = $this->connectionService->decryptToken($mailbox['access_token']);

        try {
            $emails = ($provider === 'gmail')
                ? $this->fetchGmailMessages($accessToken, $lastSyncAt)
                : $this->fetchMicrosoftMessages($accessToken, $lastSyncAt);
        } catch (\Exception $e) {
            $this->markSyncError($mailboxId, 'Fetch failed: ' . $e->getMessage());
            return ['synced' => 0, 'errors' => [$e->getMessage()]];
        }

        $inboxService = new InboxService($this->db, $tenantId, $companyCode);
        $synced       = 0;
        $errors       = [];

        foreach ($emails as $email) {
            try {
                // Skip if already imported (idempotency via external_message_id)
                if (!empty($email['external_id']) && $this->messageExists($email['external_id'], $tenantId)) {
                    continue;
                }

                $result = $inboxService->receiveMessage('email', [
                    'sender_email' => $email['sender_email'],
                    'sender_phone' => null,
                    'body'         => $email['body'],
                    'external_id'  => $email['external_id'] ?? null,
                    'metadata'     => $email['metadata'] ?? [],
                ], 0);

                // Store external_message_id on the inbox_message for idempotency
                if (!empty($email['external_id']) && !empty($result['message_id'])) {
                    $this->db->Execute(
                        'UPDATE inbox_messages SET external_message_id = ? WHERE id = ? AND tenant_id = ?',
                        [$email['external_id'], $result['message_id'], $tenantId]
                    );
                }

                $synced++;
            } catch (\Exception $e) {
                $errors[] = ['email' => $email['external_id'] ?? 'unknown', 'error' => $e->getMessage()];
            }
        }

        // Update last_sync_at on success (even partial)
        $now = $this->now();
        if (empty($errors)) {
            $this->db->Execute(
                'UPDATE connected_mailboxes SET last_sync_at = ?, sync_status = ?, last_error = NULL, updated_at = ? WHERE id = ?',
                [$now, 'active', $now, $mailboxId]
            );
        } else {
            $this->db->Execute(
                'UPDATE connected_mailboxes SET last_sync_at = ?, updated_at = ? WHERE id = ?',
                [$now, $now, $mailboxId]
            );
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    // -------------------------------------------------------------------------
    // Send email — Requirement 13.3
    // -------------------------------------------------------------------------

    /**
     * Send an email via the connected mailbox, injecting tracking pixel and
     * rewriting links.
     *
     * @param  int   $mailboxId
     * @param  array $message   Keys: to, subject, body (HTML), message_id (inbox_message_id)
     * @return bool
     */
    public function sendEmail(int $mailboxId, array $message): bool
    {
        $rs = $this->db->Execute(
            'SELECT * FROM connected_mailboxes WHERE id = ? AND deleted_at IS NULL',
            [$mailboxId]
        );

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException("Mailbox {$mailboxId} not found.");
        }

        $mailbox     = $rs->fields;
        $provider    = $mailbox['provider'];
        $accessToken = $this->connectionService->decryptToken($mailbox['access_token']);

        // Generate tracking token
        $trackingToken = $this->generateUuid();

        // Inject tracking into HTML body
        $htmlBody = $message['body'] ?? '';
        $htmlBody = $this->injectTracking($htmlBody, $trackingToken);

        // Store tracking token on the inbox_message if provided
        if (!empty($message['message_id'])) {
            $this->db->Execute(
                'UPDATE inbox_messages SET tracking_token = ? WHERE id = ? AND tenant_id = ?',
                [$trackingToken, (int) $message['message_id'], $mailbox['tenant_id']]
            );
        }

        // Send via provider API
        if ($provider === 'gmail') {
            return $this->sendViaGmail($accessToken, $mailbox['email_address'], $message, $htmlBody);
        }

        return $this->sendViaMicrosoft($accessToken, $message, $htmlBody);
    }

    // -------------------------------------------------------------------------
    // Tracking injection — Requirement 13.4
    // -------------------------------------------------------------------------

    /**
     * Inject a 1x1 tracking pixel and rewrite all <a href> links in HTML body.
     *
     * @param  string $htmlBody
     * @param  string $trackingToken  UUID
     * @return string  Modified HTML
     */
    public function injectTracking(string $htmlBody, string $trackingToken): string
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost', '/');

        // Rewrite <a href="..."> links to tracking redirect
        $htmlBody = preg_replace_callback(
            '/<a\s([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>/i',
            function (array $matches) use ($appUrl, $trackingToken): string {
                $before = $matches[1];
                $url    = $matches[2];
                $after  = $matches[3];
                // Skip mailto: and anchor links
                if (str_starts_with($url, 'mailto:') || str_starts_with($url, '#')) {
                    return $matches[0];
                }
                $trackUrl = "{$appUrl}/api/v1/email/track/{$trackingToken}/click?url=" . urlencode($url);
                return "<a {$before}href=\"{$trackUrl}\"{$after}>";
            },
            $htmlBody
        ) ?? $htmlBody;

        // Append 1x1 tracking pixel before </body> or at end
        $pixel = "<img src=\"{$appUrl}/api/v1/email/track/{$trackingToken}/open.gif\" width=\"1\" height=\"1\" alt=\"\" style=\"display:none;\">";

        if (stripos($htmlBody, '</body>') !== false) {
            $htmlBody = str_ireplace('</body>', $pixel . '</body>', $htmlBody);
        } else {
            $htmlBody .= $pixel;
        }

        return $htmlBody;
    }

    // -------------------------------------------------------------------------
    // Gmail API helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch new messages from Gmail since last_sync_at.
     *
     * Uses Gmail API users.messages.list with q: after:{timestamp}.
     *
     * @param  string      $accessToken
     * @param  string|null $lastSyncAt  UTC datetime string
     * @return array  Normalized email payloads
     */
    private function fetchGmailMessages(string $accessToken, ?string $lastSyncAt): array
    {
        $query = 'in:inbox';
        if ($lastSyncAt !== null) {
            $timestamp = strtotime($lastSyncAt);
            if ($timestamp) {
                $query .= ' after:' . $timestamp;
            }
        }

        $listUrl = 'https://gmail.googleapis.com/gmail/v1/users/me/messages?'
            . http_build_query(['q' => $query, 'maxResults' => 100]);

        $listResponse = $this->httpGet($listUrl, $accessToken);
        $messages     = $listResponse['messages'] ?? [];

        $emails = [];
        foreach ($messages as $msg) {
            try {
                $detail = $this->httpGet(
                    "https://gmail.googleapis.com/gmail/v1/users/me/messages/{$msg['id']}?format=full",
                    $accessToken
                );
                $emails[] = $this->normalizeGmailMessage($detail);
            } catch (\Exception) {
                // Skip individual message failures
            }
        }

        return $emails;
    }

    /**
     * Normalize a Gmail API message into the standard inbox payload.
     *
     * @param  array $msg  Gmail API message object
     * @return array
     */
    private function normalizeGmailMessage(array $msg): array
    {
        $headers     = [];
        $headersList = $msg['payload']['headers'] ?? [];
        foreach ($headersList as $h) {
            $headers[strtolower($h['name'])] = $h['value'];
        }

        $senderEmail = null;
        $from        = $headers['from'] ?? '';
        if (preg_match('/<([^>]+)>/', $from, $m)) {
            $senderEmail = strtolower(trim($m[1]));
        } elseif (filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $senderEmail = strtolower(trim($from));
        }

        $body = $this->extractGmailBody($msg['payload'] ?? []);

        return [
            'sender_email' => $senderEmail,
            'body'         => $body ?: '(no body)',
            'external_id'  => $msg['id'] ?? null,
            'metadata'     => [
                'subject'  => $headers['subject'] ?? '',
                'date'     => $headers['date'] ?? '',
                'provider' => 'gmail',
            ],
        ];
    }

    /**
     * Extract plain-text or HTML body from a Gmail message payload.
     */
    private function extractGmailBody(array $payload): string
    {
        // Direct body
        if (!empty($payload['body']['data'])) {
            return base64_decode(strtr($payload['body']['data'], '-_', '+/'));
        }

        // Multipart — prefer text/plain, fall back to text/html
        $parts = $payload['parts'] ?? [];
        $html  = '';
        foreach ($parts as $part) {
            $mimeType = strtolower($part['mimeType'] ?? '');
            $data     = $part['body']['data'] ?? '';
            if ($mimeType === 'text/plain' && $data !== '') {
                return base64_decode(strtr($data, '-_', '+/'));
            }
            if ($mimeType === 'text/html' && $data !== '') {
                $html = base64_decode(strtr($data, '-_', '+/'));
            }
        }

        return $html;
    }

    // -------------------------------------------------------------------------
    // Microsoft 365 Graph API helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch new messages from Microsoft 365 inbox since last_sync_at.
     *
     * Uses Graph API GET /me/mailFolders/inbox/messages with $filter.
     *
     * @param  string      $accessToken
     * @param  string|null $lastSyncAt
     * @return array
     */
    private function fetchMicrosoftMessages(string $accessToken, ?string $lastSyncAt): array
    {
        $url = 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages?$top=100&$select=id,subject,from,body,receivedDateTime';

        if ($lastSyncAt !== null) {
            $iso = (new \DateTimeImmutable($lastSyncAt, new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);
            $url .= '&$filter=' . urlencode("receivedDateTime ge {$iso}");
        }

        $response = $this->httpGet($url, $accessToken);
        $messages = $response['value'] ?? [];

        $emails = [];
        foreach ($messages as $msg) {
            $emails[] = $this->normalizeMicrosoftMessage($msg);
        }

        return $emails;
    }

    /**
     * Normalize a Microsoft Graph message into the standard inbox payload.
     */
    private function normalizeMicrosoftMessage(array $msg): array
    {
        $senderEmail = strtolower(trim(
            $msg['from']['emailAddress']['address'] ?? ''
        ));

        $body = strip_tags($msg['body']['content'] ?? '');

        return [
            'sender_email' => $senderEmail ?: null,
            'body'         => $body ?: '(no body)',
            'external_id'  => $msg['id'] ?? null,
            'metadata'     => [
                'subject'  => $msg['subject'] ?? '',
                'date'     => $msg['receivedDateTime'] ?? '',
                'provider' => 'microsoft365',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Send via Gmail
    // -------------------------------------------------------------------------

    /**
     * Send an email via Gmail API (users.messages.send).
     */
    private function sendViaGmail(string $accessToken, string $fromEmail, array $message, string $htmlBody): bool
    {
        $to      = $message['to'] ?? '';
        $subject = $message['subject'] ?? '(no subject)';

        $rawMessage = "From: {$fromEmail}\r\n"
            . "To: {$to}\r\n"
            . "Subject: {$subject}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "\r\n"
            . $htmlBody;

        $encoded = rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');

        $response = $this->httpPost(
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
            $accessToken,
            ['raw' => $encoded]
        );

        return !empty($response['id']);
    }

    // -------------------------------------------------------------------------
    // Send via Microsoft 365
    // -------------------------------------------------------------------------

    /**
     * Send an email via Microsoft Graph API (sendMail).
     */
    private function sendViaMicrosoft(string $accessToken, array $message, string $htmlBody): bool
    {
        $to      = $message['to'] ?? '';
        $subject = $message['subject'] ?? '(no subject)';

        $payload = [
            'message' => [
                'subject' => $subject,
                'body'    => ['contentType' => 'HTML', 'content' => $htmlBody],
                'toRecipients' => [
                    ['emailAddress' => ['address' => $to]],
                ],
            ],
            'saveToSentItems' => true,
        ];

        $this->httpPost(
            'https://graph.microsoft.com/v1.0/me/sendMail',
            $accessToken,
            $payload
        );

        return true; // Graph returns 202 with no body on success
    }

    // -------------------------------------------------------------------------
    // HTTP helpers
    // -------------------------------------------------------------------------

    /**
     * Perform an authenticated GET request.
     *
     * @param  string $url
     * @param  string $accessToken
     * @return array  Decoded JSON response
     */
    private function httpGet(string $url, string $accessToken): array
    {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => "Authorization: Bearer {$accessToken}\r\nAccept: application/json\r\n",
                'timeout' => 15,
            ],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new \RuntimeException("HTTP GET failed for: {$url}");
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON response from: {$url}");
        }

        return $data;
    }

    /**
     * Perform an authenticated POST request.
     *
     * @param  string $url
     * @param  string $accessToken
     * @param  array  $payload
     * @return array  Decoded JSON response (may be empty for 202 responses)
     */
    private function httpPost(string $url, string $accessToken, array $payload): array
    {
        $json = json_encode($payload);
        $ctx  = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $json,
                'timeout' => 15,
            ],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || $body === '') {
            return []; // 202 No Content is valid for sendMail
        }

        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Check if an email with the given external_message_id already exists.
     */
    private function messageExists(string $externalId, string $tenantId): bool
    {
        $rs = $this->db->Execute(
            'SELECT id FROM inbox_messages WHERE external_message_id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1',
            [$externalId, $tenantId]
        );
        return $rs !== false && !$rs->EOF;
    }

    /**
     * Mark a mailbox sync as failed with an error message.
     */
    private function markSyncError(int $mailboxId, string $error): void
    {
        $now = $this->now();
        $this->db->Execute(
            'UPDATE connected_mailboxes SET sync_status = ?, last_error = ?, updated_at = ? WHERE id = ?',
            ['error', $error, $now, $mailboxId]
        );
    }

    /**
     * Generate a UUID v4.
     */
    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
