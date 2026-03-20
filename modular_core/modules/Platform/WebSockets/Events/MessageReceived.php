<?php

namespace ModularCore\Modules\Platform\WebSockets\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * MessageReceived: Real-time Omnichannel Inbox Sync (Requirement 17.1)
 */
class MessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $tenantId;
    public $senderName;
    public $channelIcon;

    public function __construct($message, $tenantId, $senderName, $channelIcon)
    {
        $this->message = $message;
        $this->tenantId = $tenantId;
        $this->senderName = $senderName;
        $this->channelIcon = $channelIcon;
    }

    /**
     * Requirement 17.3: Private Tenant Channel Authorization
     */
    public function broadcastOn()
    {
        return new PrivateChannel('tenant.' . $this->tenantId . '.messages');
    }

    /**
     * Requirement 17.2: Consistent Event Payload
     */
    public function broadcastWith()
    {
        return [
            'id' => $this->message['id'],
            'text' => $this->message['text'],
            'sender_name' => $this->senderName,
            'channel' => $this->message['channel'],
            'channel_icon' => $this->channelIcon,
            'timestamp' => now()->toIso8601String(),
            'ai_intent' => $this->message['ai_intent'] ?? 'neutral',
        ];
    }

    public function broadcastAs()
    {
        return 'message.received';
    }
}
