#!/usr/bin/env php
<?php
/**
 * modular_core/cli/rotate_jwt_keys.php
 *
 * CLI entry point for JWT key rotation.
 * Instantiates JwtKeyRotationService and calls rotate().
 *
 * Usage:
 *   php /app/modular_core/cli/rotate_jwt_keys.php
 *
 * Exit codes:
 *   0 — rotation completed (or not needed)
 *   1 — rotation failed
 *
 * Requirements: 42.7
 */

declare(strict_types=1);

// Bootstrap autoloader
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];

$autoloaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloaded = true;
        break;
    }
}

if (!$autoloaded) {
    fwrite(STDERR, "[rotate_jwt_keys] ERROR: Could not find vendor/autoload.php\n");
    exit(1);
}

// Load environment / config
$configPath = __DIR__ . '/../config/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

use Platform\Auth\JwtKeyRotationService;

// -------------------------------------------------------------------------
// Bootstrap DB and Redis connections
// -------------------------------------------------------------------------

try {
    // ADOdb connection — expects DB_DSN or individual DB_* constants from config
    if (!defined('DB_HOST')) {
        throw new \RuntimeException('Database configuration not found. Ensure config.php defines DB_HOST, DB_NAME, DB_USER, DB_PASS.');
    }

    $db = ADONewConnection('postgres');
    $db->Connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $db->SetFetchMode(ADODB_FETCH_ASSOC);
} catch (\Throwable $e) {
    fwrite(STDERR, "[rotate_jwt_keys] ERROR: DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

try {
    // Redis connection — expects REDIS_HOST / REDIS_PORT constants from config
    $redisHost = defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1';
    $redisPort = defined('REDIS_PORT') ? (int) REDIS_PORT : 6379;

    $redis = new \Redis();
    if (!$redis->connect($redisHost, $redisPort)) {
        throw new \RuntimeException("Could not connect to Redis at {$redisHost}:{$redisPort}");
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "[rotate_jwt_keys] ERROR: Redis connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// -------------------------------------------------------------------------
// Run rotation
// -------------------------------------------------------------------------

try {
    $service = new JwtKeyRotationService($db, $redis);

    if (!$service->shouldRotate()) {
        echo "[rotate_jwt_keys] No rotation needed — last rotation was less than 90 days ago.\n";
        exit(0);
    }

    $service->rotate();
    echo "[rotate_jwt_keys] SUCCESS: JWT key rotation completed at " . date('Y-m-d H:i:s') . "\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "[rotate_jwt_keys] ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
