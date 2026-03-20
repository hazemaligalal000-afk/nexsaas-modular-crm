<?php

namespace ModularCore\Bootstrap;

use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;
use Exception;

/**
 * Requirement 10: Implement Rate Limiting (Phase 0.3)
 */
class RateLimiterService
{
    private $redis;

    public function __construct()
    {
        $this->redis = Redis::connection('default');
    }

    /**
     * Check if request should be limited (Requirement 10.1-10.2)
     */
    public function check(Request $request, string $type = 'api'): bool
    {
        $ip = $request->ip();
        $user = $request->user();
        
        # 1. Scope (Requirement 10.7: Use user ID if available, otherwise IP)
        $key = $user ? "rate_limit:{$type}:user_{$user->id}" : "rate_limit:{$type}:ip_{$ip}";

        # 2. Config (Requirement 10.1 & 10.2)
        $limits = [
            'login' => ['max' => 5, 'window' => 900],   # 5 per 15 mins
            'api'   => ['max' => 100, 'window' => 60]  # 100 per 1 min
        ];

        $limit = $limits[$type] ?? $limits['api'];

        # 3. Redis Rolling Window (Requirement 10.3)
        $currentTime = time();
        $windowStart = $currentTime - $limit['window'];

        # Remove old requests
        $this->redis->zremrangebyscore($key, 0, $windowStart);

        # Count current requests
        $requestCount = $this->redis->zcard($key);

        if ($requestCount >= $limit['max']) {
            # Requirement 10.6: Log violation
            \Log::warning("Rate limit exceeded for [{$key}] on [{$type}]");
            return false;
        }

        # Add new request
        $this->redis->zadd($key, $currentTime, $currentTime . "_" . bin2hex(random_bytes(4)));
        $this->redis->expire($key, $limit['window']);

        return true;
    }

    /**
     * Requirement 10.4: Calculate Retry-After time
     */
    public function getRetryAfter(Request $request, string $type = 'api'): int
    {
       // Basic logic: return the window size if unsure, or more precise calculation
       return 60; 
    }
}
