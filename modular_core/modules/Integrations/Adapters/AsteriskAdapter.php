<?php
/**
 * Integrations/Adapters/AsteriskAdapter.php
 *
 * Asterisk AMI (Asterisk Manager Interface) — TCP socket, text-based.
 * Most deployed open-source PBX in Egypt.
 */

declare(strict_types=1);

namespace Integrations\Adapters;

class AsteriskAdapter extends BaseAdapter
{
    /** @var resource|false */
    private $socket = false;

    public function connect(): void
    {
        $this->socket = fsockopen(
            $this->config['host'],
            (int)($this->config['port'] ?? 5038),
            $errno,
            $errstr,
            10
        );
        if ($this->socket === false) {
            throw new \RuntimeException("Cannot connect to Asterisk AMI: {$errstr}");
        }
        // Read banner
        fgets($this->socket, 1024);
        // Login
        $this->sendCommand(
            "Action: Login\r\n" .
            "Username: {$this->config['username']}\r\n" .
            "Secret: {$this->config['secret']}\r\n\r\n"
        );
    }

    public function makeCall(string $to, string $from, string $context = 'from-internal'): array
    {
        $this->connect();
        $actionId = uniqid('nxs_');
        $this->sendCommand(
            "Action: Originate\r\n" .
            "ActionID: {$actionId}\r\n" .
            "Channel: SIP/{$from}\r\n" .
            "Exten: {$to}\r\n" .
            "Context: {$context}\r\n" .
            "Priority: 1\r\n" .
            "Timeout: 30000\r\n" .
            "CallerID: NexSaaS <{$from}>\r\n" .
            "Async: true\r\n\r\n"
        );
        $this->disconnect();
        return ['action_id' => $actionId, 'status' => 'initiated'];
    }

    public function sendSMS(string $to, string $body): array
    {
        return ['status' => 'not_supported', 'message' => 'Use SMS gateway for SMS'];
    }

    public function sendWhatsApp(string $to, string $message, array $media = []): array
    {
        return ['status' => 'not_supported'];
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        return true; // AMI events come from internal network
    }

    public function parseInboundWebhook(array $payload): array
    {
        $event = $payload['Event'] ?? '';
        return match($event) {
            'Newchannel' => [
                'type'     => 'call_started',
                'from'     => $payload['CallerIDNum'] ?? '',
                'to'       => $payload['Exten']       ?? '',
                'body'     => '',
                'call_sid' => $payload['Uniqueid']    ?? '',
                'duration' => 0,
                'recording'=> '',
                'raw'      => $payload,
            ],
            'Hangup' => [
                'type'     => 'call_ended',
                'from'     => $payload['CallerIDNum'] ?? '',
                'to'       => $payload['Exten']       ?? '',
                'body'     => '',
                'call_sid' => $payload['Uniqueid']    ?? '',
                'duration' => (int)($payload['Duration'] ?? 0),
                'recording'=> '',
                'raw'      => $payload,
            ],
            default => ['type' => 'unknown', 'raw' => $payload],
        };
    }

    public function sendCommand(string $cmd): string
    {
        if ($this->socket === false) {
            return '';
        }
        fwrite($this->socket, $cmd);
        return (string)stream_get_contents($this->socket, 1024);
    }

    public function disconnect(): void
    {
        if ($this->socket !== false) {
            $this->sendCommand("Action: Logoff\r\n\r\n");
            fclose($this->socket);
            $this->socket = false;
        }
    }
}
