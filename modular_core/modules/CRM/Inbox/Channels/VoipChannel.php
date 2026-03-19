<?php
/**
 * CRM/Inbox/Channels/VoipChannel.php
 *
 * VoIP channel handler using SIP (Session Initiation Protocol).
 * Inbound call events arrive via SIP server webhook or AMI (Asterisk Manager Interface).
 * Outbound calls are initiated via SIP INVITE or a click-to-call API.
 *
 * Requirements: 12.1
 */

declare(strict_types=1);

namespace CRM\Inbox\Channels;

class VoipChannel
{
    private array $config;

    /**
     * @param array $config  Keys:
     *   - sip_host         (string)  SIP server hostname or IP
     *   - sip_port         (int)     SIP port (default 5060)
     *   - sip_user         (string)  SIP username / extension
     *   - sip_password     (string)  SIP password
     *   - ami_host         (string)  Asterisk AMI host (optional)
     *   - ami_port         (int)     Asterisk AMI port (default 5038)
     *   - ami_user         (string)  AMI username
     *   - ami_secret       (string)  AMI secret
     *   - click_to_call_url (string) External click-to-call API endpoint (optional)
     *   - recording_dir    (string)  Directory for call recordings
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'sip_port' => 5060,
            'ami_port' => 5038,
        ], $config);
    }

    // -------------------------------------------------------------------------
    // Inbound — SIP/AMI event parsing
    // -------------------------------------------------------------------------

    /**
     * Parse an inbound call event (from SIP server webhook or AMI) into a
     * normalised message payload for InboxService::receiveMessage().
     *
     * Expected payload keys (AMI-style or custom webhook):
     *   - CallerID   (string)  Caller phone number (E.164 or local)
     *   - Channel    (string)  SIP channel identifier
     *   - UniqueID   (string)  Unique call ID
     *   - Duration   (int)     Call duration in seconds (0 if still active)
     *   - Recording  (string)  Path to recording file (optional)
     *
     * @param  array $eventPayload
     * @return array  Normalised: ['sender_phone', 'sender_email', 'body', 'external_id', 'metadata']
     *
     * @throws \InvalidArgumentException on missing CallerID
     */
    public function parseInboundEvent(array $eventPayload): array
    {
        $callerId = trim($eventPayload['CallerID'] ?? $eventPayload['caller_id'] ?? '');

        if ($callerId === '') {
            throw new \InvalidArgumentException('VoipChannel: missing CallerID in event payload.');
        }

        $duration  = (int) ($eventPayload['Duration'] ?? $eventPayload['duration'] ?? 0);
        $uniqueId  = $eventPayload['UniqueID'] ?? $eventPayload['unique_id'] ?? null;
        $recording = $eventPayload['Recording'] ?? $eventPayload['recording'] ?? null;

        // Build a human-readable body summarising the call
        $body = $duration > 0
            ? sprintf('Inbound call from %s — duration: %s', $callerId, $this->formatDuration($duration))
            : sprintf('Inbound call from %s', $callerId);

        return [
            'sender_phone' => $callerId,
            'sender_email' => null,
            'body'         => $body,
            'external_id'  => $uniqueId,
            'metadata'     => [
                'channel'   => $eventPayload['Channel'] ?? null,
                'unique_id' => $uniqueId,
                'duration'  => $duration,
                'recording' => $recording,
                'direction' => 'inbound',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Outbound — click-to-call / SIP INVITE
    // -------------------------------------------------------------------------

    /**
     * Initiate an outbound call to the contact's phone number.
     *
     * Uses a configured click-to-call API endpoint if available, otherwise
     * initiates via Asterisk AMI originate action.
     *
     * @param  array  $conversation  Conversation row
     * @param  string $body          Optional note/script for the agent (not transmitted to callee)
     *
     * @throws \RuntimeException on dial failure or missing configuration
     */
    public function send(array $conversation, string $body): void
    {
        $to = $conversation['metadata']['sender_phone']
            ?? $conversation['sender_phone']
            ?? null;

        if (empty($to)) {
            throw new \RuntimeException('VoipChannel::send: no recipient phone number available.');
        }

        // Prefer click-to-call API if configured
        if (!empty($this->config['click_to_call_url'])) {
            $this->clickToCall($to, $body);
            return;
        }

        // Fall back to Asterisk AMI originate
        if (!empty($this->config['ami_host'])) {
            $this->amiOriginate($to);
            return;
        }

        throw new \RuntimeException('VoipChannel::send: no outbound call mechanism configured (click_to_call_url or ami_host required).');
    }

    // -------------------------------------------------------------------------
    // Call recording retrieval
    // -------------------------------------------------------------------------

    /**
     * Get the recording file path for a call by its unique ID.
     *
     * @param  string $uniqueId
     * @return string|null  Absolute path to recording file, or null if not found
     */
    public function getRecordingPath(string $uniqueId): ?string
    {
        $dir = rtrim($this->config['recording_dir'] ?? '', '/');
        if ($dir === '') {
            return null;
        }

        foreach (['wav', 'mp3', 'ogg'] as $ext) {
            $path = "{$dir}/{$uniqueId}.{$ext}";
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Initiate a call via a click-to-call HTTP API.
     *
     * @param  string $to    Destination phone number
     * @param  string $notes Optional agent notes
     *
     * @throws \RuntimeException on HTTP error
     */
    private function clickToCall(string $to, string $notes = ''): void
    {
        $url     = $this->config['click_to_call_url'];
        $payload = json_encode(['to' => $to, 'from' => $this->config['sip_user'] ?? '', 'notes' => $notes]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new \RuntimeException("VoipChannel::clickToCall: cURL error: {$curlError}");
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            throw new \RuntimeException("VoipChannel::clickToCall: API error (HTTP {$httpStatus}): {$response}");
        }
    }

    /**
     * Originate a call via Asterisk AMI.
     *
     * @param  string $to  Destination phone number
     *
     * @throws \RuntimeException on AMI connection or originate failure
     */
    private function amiOriginate(string $to): void
    {
        $host   = $this->config['ami_host'];
        $port   = (int) ($this->config['ami_port'] ?? 5038);
        $user   = $this->config['ami_user'] ?? '';
        $secret = $this->config['ami_secret'] ?? '';
        $from   = $this->config['sip_user'] ?? 'agent';

        $socket = @fsockopen($host, $port, $errno, $errstr, 5);
        if ($socket === false) {
            throw new \RuntimeException("VoipChannel::amiOriginate: cannot connect to AMI at {$host}:{$port} — {$errstr}");
        }

        try {
            // Read banner
            fgets($socket, 1024);

            // Login
            fwrite($socket, "Action: Login\r\nUsername: {$user}\r\nSecret: {$secret}\r\n\r\n");
            $this->readAmiResponse($socket);

            // Originate
            $actionId = uniqid('nexsaas_', true);
            fwrite($socket, implode("\r\n", [
                'Action: Originate',
                "Channel: SIP/{$from}",
                "Exten: {$to}",
                'Context: default',
                'Priority: 1',
                "ActionID: {$actionId}",
                "Async: true",
                '',
                '',
            ]));

            $this->readAmiResponse($socket);

            // Logoff
            fwrite($socket, "Action: Logoff\r\n\r\n");
        } finally {
            fclose($socket);
        }
    }

    /**
     * Read an AMI response block (terminated by blank line).
     *
     * @param  resource $socket
     * @return string
     */
    private function readAmiResponse($socket): string
    {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 1024);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (trim($line) === '') {
                break; // End of AMI response block
            }
        }
        return $response;
    }

    /**
     * Format a duration in seconds as MM:SS.
     *
     * @param  int $seconds
     * @return string
     */
    private function formatDuration(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $secs    = $seconds % 60;
        return sprintf('%d:%02d', $minutes, $secs);
    }
}
