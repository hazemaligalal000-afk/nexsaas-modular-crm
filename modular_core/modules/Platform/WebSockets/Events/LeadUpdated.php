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
 * LeadUpdated: Real-time Lead Dashboard Updates (Requirement 18.1)
 */
class LeadUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $leadId;
    public $tenantId;
    public $updatedFields;
    public $updatedBy;

    public function __construct($leadId, $tenantId, $updatedFields, $updatedBy)
    {
        $this->leadId = $leadId;
        $this->tenantId = $tenantId;
        $this->updatedFields = $updatedFields;
        $this->updatedBy = $updatedBy;
    }

    /**
     * Requirement 18.3: Private Tenant Channel Authorization
     */
    public function broadcastOn()
    {
        return new PrivateChannel('tenant.' . $this->tenantId . '.leads');
    }

    /**
     * Requirement 18.2: Consistent Event Payload
     */
    public function broadcastWith()
    {
        return [
            'lead_id' => $this->leadId,
            'updated_fields' => $this->updatedFields,
            'updated_by' => $this->updatedBy,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs()
    {
        return 'lead.updated';
    }
}
