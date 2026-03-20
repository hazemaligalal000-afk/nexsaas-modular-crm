<?php

namespace ModularCore\Modules\Omnichannel\WhatsApp;

use GuzzleHttp\Client;
use Exception;

/**
 * WhatsApp Service: Native Meta/Twilio Business API Integration (Omnichannel)
 * Critical for high-volume MENA customer support.
 */
class WhatsAppService
{
    private $apiEndpoint = 'https://graph.facebook.com/v19.0';
    private $accessToken;
    private $phoneNumberId;

    public function __construct()
    {
        $this->accessToken = env('WHATSAPP_ACCESS_TOKEN');
        $this->phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
    }

    /**
     * Requirement: Send Outgoing Message (WhatsApp Native)
     */
    public function sendMessage($to, $message)
    {
        $client = new Client();
        try {
            $response = $client->post("{$this->apiEndpoint}/{$this->phoneNumberId}/messages", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            return [
                'success' => true,
                'message_id' => $body['messages'][0]['id'] ?? null,
                'status' => 'sent',
            ];
        } catch (Exception $e) {
            \Log::error("WhatsApp Send Failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'status' => 'failed'];
        }
    }

    /**
     * Requirement: Send Template Message (HSA/Business)
     */
    public function sendTemplate($to, $templateName, $languageCode = 'en_US', $components = [])
    {
        $client = new Client();
        try {
            $response = $client->post("{$this->apiEndpoint}/{$this->phoneNumberId}/messages", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => $languageCode],
                        'components' => $components,
                    ],
                ],
            ]);

            return [
                'success' => true,
                'status' => 'template_sent',
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Requirement: Webhook Parser (Incoming Messages)
     */
    public function parseWebhook($payload)
    {
        // Extracts sender, text, and metadata from Meta Webhook payload
        $entry = $payload['entry'][0]['changes'][0]['value'] ?? null;
        if ($entry && isset($entry['messages'][0])) {
            $message = $entry['messages'][0];
            return [
                'from' => $message['from'],
                'text' => $message['text']['body'] ?? '',
                'msg_id' => $message['id'],
                'timestamp' => $message['timestamp'],
                'type' => $message['type'],
            ];
        }
        return null;
    }
}
