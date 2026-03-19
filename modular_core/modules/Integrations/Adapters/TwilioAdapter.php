<?php
/**
 * Integrations/Adapters/TwilioAdapter.php
 *
 * Twilio — global CPaaS: voice, SMS, WhatsApp, IVR.
 */

declare(strict_types=1);

namespace Integrations\Adapters;

class TwilioAdapter extends BaseAdapter
{
    const BASE_URL = 'https://api.twilio.com/2010-04-01';

    public function makeCall(string $to, string $from, string $twimlUrl): array
    {
        return $this->post(
            self::BASE_URL . "/Accounts/{$this->config['account_sid']}/Calls.json",
            ['To' => $to, 'From' => $from, 'Url' => $twimlUrl],
            $this->basicAuth()
        );
    }

    public function sendSMS(string $to, string $body): array
    {
        return $this->post(
            self::BASE_URL . "/Accounts/{$this->config['account_sid']}/Messages.json",
            ['To' => $to, 'From' => $this->config['from_number'], 'Body' => $body],
            $this->basicAuth()
        );
    }

    public function sendWhatsApp(string $to, string $message, array $media = []): array
    {
        $payload = [
            'To'   => 'whatsapp:' . $to,
            'From' => 'whatsapp:' . $this->config['whatsapp_number'],
            'Body' => $message,
        ];
        if (!empty($media)) {
            $payload['MediaUrl'] = $media[0];
        }
        return $this->post(
            self::BASE_URL . "/Accounts/{$this->config['account_sid']}/Messages.json",
            $payload,
            $this->basicAuth()
        );
    }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $signature = $headers['X-Twilio-Signature'] ?? '';
        $url       = $this->config['webhook_url'] ?? '';
        $authToken = $this->config['auth_token']  ?? '';
        $computed  = base64_encode(hash_hmac('sha1', $url . $rawBody, $authToken, true));
        return hash_equals($computed, $signature);
    }

    public function parseInboundWebhook(array $payload): array
    {
        $status = strtolower($payload['CallStatus'] ?? $payload['SmsStatus'] ?? '');
        $type   = match(true) {
            str_contains($status, 'ringing')     => 'call_started',
            str_contains($status, 'completed')   => 'call_ended',
            str_contains($status, 'no-answer')   => 'call_ended',
            isset($payload['Body'])              => 'sms_received',
            default                              => 'call_started',
        };

        return [
            'type'      => $type,
            'from'      => $payload['From']         ?? '',
            'to'        => $payload['To']           ?? '',
            'body'      => $payload['Body']         ?? '',
            'call_sid'  => $payload['CallSid']      ?? $payload['SmsSid'] ?? '',
            'duration'  => (int)($payload['CallDuration'] ?? 0),
            'recording' => $payload['RecordingUrl'] ?? '',
            'status'    => $payload['CallStatus']   ?? '',
            'raw'       => $payload,
        ];
    }

    /** Build TwiML XML for IVR flows */
    public static function buildIVR(array $options): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Response>';
        foreach ($options['steps'] ?? [] as $step) {
            $xml .= match($step['type']) {
                'say'    => "<Say language=\"ar-EG\">{$step['text']}</Say>",
                'gather' => "<Gather numDigits=\"1\" action=\"{$step['action']}\"><Say>{$step['prompt']}</Say></Gather>",
                'dial'   => "<Dial>{$step['number']}</Dial>",
                'record' => "<Record maxLength=\"{$step['max']}\" action=\"{$step['action']}\"/>",
                default  => '',
            };
        }
        return $xml . '</Response>';
    }
}
