<?php
/**
 * Redis-backed Rate Limiter
 * (Requirement 0.3: Rate limiting on login and API endpoints)
 */

class RateLimiter {
    private $redis;
    private $prefix = 'rate_limit:';

    public function __construct() {
        if (!class_exists('Redis')) {
            // Fallback to no limiting if Redis is not available
            return;
        }

        try {
            $this->redis = new \Redis();
            $host = getenv('REDIS_HOST') ?: '127.0.0.1';
            $port = getenv('REDIS_PORT') ?: 6379;
            $pass = getenv('REDIS_PASSWORD');
            
            $this->redis->connect($host, $port);
            if ($pass) {
                $this->redis->auth($pass);
            }
        } catch (Exception $e) {
            $this->redis = null;
        }
    }

    public function check($key, $limit = 5, $minutes = 15) {
        if (!$this->redis) return true;

        $key = $this->prefix . $key;
        $current = $this->redis->get($key);

        if ($current !== false && $current >= $limit) {
            return false;
        }

        if ($current === false) {
            $this->redis->setex($key, $minutes * 60, 1);
        } else {
            $this->redis->incr($key);
        }

        return true;
    }

    public function getRemaining($key, $limit = 5) {
        if (!$this->redis) return $limit;
        $current = $this->redis->get($this->prefix . $key);
        return $current === false ? $limit : max(0, $limit - $current);
    }
}
