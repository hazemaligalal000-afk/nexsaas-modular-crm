<?php

namespace ModularCore\Modules\Platform\Analytics;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Exception;

/**
 * Task 18.2: Analytics Event Emitter (Phase 4)
 */
class EventEmitter
{
    private $connection;
    private $channel;
    private $queue = 'business_analytics';

    public function __construct()
    {
        $this->connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST', 'localhost'),
            env('RABBITMQ_PORT', '5672'),
            env('RABBITMQ_USER', 'guest'),
            env('RABBITMQ_PASS', 'guest')
        );
        $this->channel = $this->connection->channel();
        $this->channel->queue_declare($this->queue, false, true, false, false);
    }

    /**
     * Requirement 15.1-15.6: Async Event Emission
     */
    public function emit(string $eventType, array $data = []): void
    {
        $payload = array_merge([
            'event_type' => $eventType,
            'tenant_id'  => request()->user() ? request()->user()->tenant_id : null,
            'user_id'    => request()->user() ? request()->user()->id : null,
            'timestamp'  => microtime(true),
            'ip'         => request()->ip(),
            'metadata'   => $data
        ]);

        $msg = new AMQPMessage(json_encode($payload), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $this->channel->basic_publish($msg, '', $this->queue);
    }

    /**
     * Common Event Shortcuts
     */
    public function logLoginAttempt() { $this->emit('USER_LOGIN'); }
    public function logLeadCreated($leadId) { $this->emit('LEAD_CREATED', ['lead_id' => $leadId]); }
    public function logCheckoutSuccess($amount) { $this->emit('CHECKOUT_SUCCESS', ['amount' => $amount]); }
}
