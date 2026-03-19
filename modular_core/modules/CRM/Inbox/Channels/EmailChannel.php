<?php
/**
 * CRM/Inbox/Channels/EmailChannel.php
 *
 * Email channel handler for the Omnichannel Inbox.
 * Supports IMAP for inbound polling and SMTP for outbound sending.
 *
 * Requirements: 12.1, 13.3
 */

declare(strict_types=1);

namespace CRM\Inbox\Channels;

class EmailChannel
{
    /** @var array IMAP/SMTP configuration */
    private array $config;

    /**
     * @param array $config  Keys:
     *   - imap_host     (string)
     *   - imap_port     (int, default 993)
     *   - imap_user     (string)
     *   - imap_password (string)
     *   - imap_flags    (string, default '{host:993/imap/ssl}INBOX')
     *   - smtp_host     (string)
     *   - smtp_port     (int, default 587)
     *   - smtp_user     (string)
     *   - smtp_password (string)
     *   - smtp_from     (string)
     *   - smtp_from_name (string)
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'imap_port'  => 993,
            'smtp_port'  => 587,
        ], $config);
    }

    // -------------------------------------------------------------------------
    // Inbound — IMAP polling
    // -------------------------------------------------------------------------

    /**
     * Poll the IMAP mailbox for new messages.
     *
     * Returns an array of normalised message payloads ready for InboxService::receiveMessage().
     *
     * @return array  Each element: ['sender_email', 'body', 'subject', 'external_id', 'metadata']
     *
     * @throws \RuntimeException if IMAP connection fails
     */
    public function pollInbound(): array
    {
        if (!function_exists('imap_open')) {
            throw new \RuntimeException('IMAP extension is not available.');
        }

        $mailbox = $this->buildImapMailbox();
        $conn    = @imap_open($mailbox, $this->config['imap_user'] ?? '', $this->config['imap_password'] ?? '');

        if ($conn === false) {
            throw new \RuntimeException('EmailChannel: IMAP connection failed: ' . imap_last_error());
        }

        try {
            $unseen = imap_search($conn, 'UNSEEN');
            if ($unseen === false) {
                return [];
            }

            $messages = [];
            foreach ($unseen as $msgNo) {
                $header = imap_headerinfo($conn, $msgNo);
                $body   = $this->fetchBody($conn, $msgNo);

                $senderEmail = null;
                if (!empty($header->from[0]->mailbox) && !empty($header->from[0]->host)) {
                    $senderEmail = $header->from[0]->mailbox . '@' . $header->from[0]->host;
                }

                $messages[] = [
                    'sender_email' => $senderEmail,
                    'sender_phone' => null,
                    'body'         => $body,
                    'external_id'  => $header->message_id ?? null,
                    'metadata'     => [
                        'subject'  => isset($header->subject) ? imap_utf8($header->subject) : '',
                        'msg_no'   => $msgNo,
                    ],
                ];

                // Mark as seen
                imap_setflag_full($conn, (string) $msgNo, '\\Seen');
            }

            return $messages;
        } finally {
            imap_close($conn);
        }
    }

    // -------------------------------------------------------------------------
    // Outbound — SMTP sending
    // -------------------------------------------------------------------------

    /**
     * Send an outbound email reply.
     *
     * @param  array  $conversation  Conversation row (must contain metadata with recipient email)
     * @param  string $body          Plain-text message body
     *
     * @throws \RuntimeException on send failure
     */
    public function send(array $conversation, string $body): void
    {
        $to = $conversation['metadata']['sender_email']
            ?? $conversation['sender_email']
            ?? null;

        if (empty($to)) {
            throw new \RuntimeException('EmailChannel::send: no recipient email address available.');
        }

        $subject  = $conversation['metadata']['subject'] ?? 'Re: Your message';
        $from     = $this->config['smtp_from'] ?? '';
        $fromName = $this->config['smtp_from_name'] ?? 'Support';

        $headers  = "From: {$fromName} <{$from}>\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        // Use PHP's mail() as a baseline; production deployments should inject
        // a proper mailer (PHPMailer / Symfony Mailer) via the config.
        if (isset($this->config['mailer']) && is_callable($this->config['mailer'])) {
            ($this->config['mailer'])($to, $subject, $body, $headers);
            return;
        }

        $sent = mail($to, $subject, $body, $headers);
        if (!$sent) {
            throw new \RuntimeException("EmailChannel::send: mail() failed for recipient {$to}.");
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function buildImapMailbox(): string
    {
        $host  = $this->config['imap_host'] ?? 'localhost';
        $port  = (int) ($this->config['imap_port'] ?? 993);
        $flags = $this->config['imap_flags'] ?? '/imap/ssl';
        return "{{$host}:{$port}{$flags}}INBOX";
    }

    /**
     * Fetch the plain-text body of an IMAP message.
     *
     * @param  resource $conn
     * @param  int      $msgNo
     * @return string
     */
    private function fetchBody($conn, int $msgNo): string
    {
        $structure = imap_fetchstructure($conn, $msgNo);

        if ($structure->type === 0) {
            // Simple plain-text message
            $body = imap_fetchbody($conn, $msgNo, '1');
            return $this->decodeBody($body, $structure->encoding ?? 0);
        }

        // Multipart — find the first text/plain part
        if (!empty($structure->parts)) {
            foreach ($structure->parts as $partNo => $part) {
                if ($part->type === 0 && strtolower($part->subtype ?? '') === 'plain') {
                    $body = imap_fetchbody($conn, $msgNo, (string) ($partNo + 1));
                    return $this->decodeBody($body, $part->encoding ?? 0);
                }
            }
        }

        return imap_fetchbody($conn, $msgNo, '1');
    }

    /**
     * Decode an IMAP body part based on its encoding.
     *
     * @param  string $body
     * @param  int    $encoding  IMAP encoding constant
     * @return string
     */
    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($body),                  // BASE64
            4 => quoted_printable_decode($body),        // QUOTED-PRINTABLE
            default => $body,
        };
    }
}
