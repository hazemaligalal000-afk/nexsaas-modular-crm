<?php
namespace Core\Performance;

use Core\Config;
use Redis;

/**
 * CacheManager: Handles Redis caching with fallback to database/memory.
 * Requirement 41.3, 41.4
 */
class CacheManager {
    private static $instance = null;
    private $redis = null;
    private $isAvailable = false;

    private function __construct() {
        try {
            $this->redis = new Redis();
            $config = Config::get('redis');
            if ($this->redis->connect($config['host'], $config['port'])) {
                $this->isAvailable = true;
            }
        } catch (\Exception $e) {
            error_log("Redis unavailable: " . $e->getMessage());
            $this->isAvailable = false;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $key) {
        if (!$this->isAvailable) return null;
        return $this->redis->get($key);
    }

    public function set(string $key, $value, int $ttl = 3600) {
        if (!$this->isAvailable) return false;
        return $this->redis->setex($key, $ttl, $value);
    }

    public function remember(string $key, int $ttl, callable $callback) {
        $value = $this->get($key);
        if ($value !== null) return $value;

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }
}
