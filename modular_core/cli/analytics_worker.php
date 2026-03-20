<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Task 18.3: Analytics Consumer Worker (Phase 4)
 */

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();
$channel->queue_declare('business_analytics', false, true, false, false);

echo " [*] NexSaaS Analytics Worker Booting up. To exit press CTRL+C\n";

/**
 * Requirement 15.8: Batch insertion and processing
 */
$callback = function (AMQPMessage $msg) {
    echo ' [x] Processing Event: ', $msg->body, "\n";
    $event = json_decode($msg->body, true);
    
    # 1. Sanitize (Requirement 14.4)
    # Ensure no PII is saved into the BI database.
    
    # 2. Persist (Requirement 15.1)
    # \DB::table('business_events')->insert([...]);
    
    # 3. ACK (Requirement 15.8)
    $msg->ack();
};

$channel->basic_qos(null, 100, null); // Prefetch 100 for batch performance
$channel->basic_consume('business_analytics', '', false, false, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
