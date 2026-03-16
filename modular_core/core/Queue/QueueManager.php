<?php
/**
 * Core/Queue/QueueManager.php
 * Handles pushing asynchronous tasks (Webhooks, AI, Comms) to RabbitMQ.
 */

namespace Core\Queue;

class QueueManager {
    
    /**
     * Pushes a job to a specific queue.
     */
    public static function push($queueName, $data) {
        $tenantId = \Core\TenantEnforcer::getTenantId();
        
        // Wrap data with tenant context to ensure isolation in the worker
        $payload = json_encode([
            'tenant_id' => $tenantId,
            'job_data' => $data,
            'timestamp' => time()
        ]);

        // In a real environment, we'd use the php-amqplib library here.
        // For this architecture demonstration, we'll log the intention.
        // \Core\Logger::info("Queued job to {$queueName}", $data);

        // Simulation of AMQP push:
        // $channel->basic_publish(new AMQPMessage($payload), '', $queueName);
        
        return true;
    }
}
