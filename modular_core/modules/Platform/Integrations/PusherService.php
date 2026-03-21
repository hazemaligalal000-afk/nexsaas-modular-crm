<?php
/**
 * ModularCore/Modules/Platform/Integrations/PusherService.php
 * Real-time Event Broadcasting via Pusher (Requirement Week 3.4)
 */

namespace ModularCore\Modules\Platform\Integrations;

class PusherService {
    
    private static $pusher = null;

    private static function init() {
        if (self::$pusher !== null) return;

        $options = [
            'cluster' => getenv('PUSHER_CLUSTER') ?: 'mt1',
            'useTLS' => true
        ];
        
        // Mocking for environments without keys
        $appId = getenv('PUSHER_APP_ID') ?: 'nexsaas_mock_id';
        $key = getenv('PUSHER_KEY') ?: 'nexsaas_mock_key';
        $secret = getenv('PUSHER_SECRET') ?: 'nexsaas_mock_secret';

        if (class_exists('Pusher\Pusher')) {
            self::$pusher = new \Pusher\Pusher($key, $secret, $appId, $options);
        }
    }

    /**
     * Trigger a real-time event on a channel
     */
    public static function trigger(string $channel, string $event, array $data) {
        self::init();
        
        if (self::$pusher) {
            try {
                self::$pusher->trigger($channel, $event, $data);
            } catch (\Exception $e) {
                // Log but don't break the flow
                error_log("Pusher Error: " . $e->getMessage());
            }
        } else {
            // Log mock broadcast
            error_log("[MOCK BROADCAST] Channel: {$channel}, Event: {$event}, Data: " . json_encode($data));
        }
    }

    /**
     * Collision Detection: Track active typing agents
     * Requirement Week 3.5
     */
    public static function trackTyping(string $tenantId, string $conversationId, string $agentName) {
        self::trigger("private-tenant-{$tenantId}", 'agent-typing', [
            'conversation_id' => $conversationId,
            'agent' => $agentName,
            'timestamp' => time()
        ]);
    }
}
