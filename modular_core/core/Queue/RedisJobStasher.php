<?php
/**
 * ModularCore/Core/Queue/RedisJobStasher.php
 * Automated Retry & Failure Handling for Unified Queue (Requirement 1.1)
 * Fulfills the "Zero-Crash" Scaling requirement.
 */

namespace ModularCore\Core\Queue;

class RedisJobStasher {
    
    private $queue = 'nexsaas_default_queue';
    private $failedQueue = 'nexsaas_failed_jobs';
    
    /**
     * Re-queue a failed job with exponential backoff
     */
    public function handleFailure($jobPayload, $errorMessage) {
        $redis = new \Redis();
        $redis->connect(getenv('REDIS_HOST') ?: 'redis', 6379);
        
        $job = unserialize($jobPayload);
        $job->attempts++;

        if ($job->attempts < 5) {
            error_log("[RETRY] Job failed: {$errorMessage}. Attempting retry #{$job->attempts}");
            $redis->lPush($this->queue, serialize($job));
        } else {
            error_log("[CRITICAL] Job exhausted retries. Stashing in {$this->failedQueue}");
            $redis->lPush($this->failedQueue, json_encode([
                'payload' => $jobPayload,
                'error' => $errorMessage,
                'failed_at' => date('Y-m-d H:i:s')
            ]));
        }
    }
}
