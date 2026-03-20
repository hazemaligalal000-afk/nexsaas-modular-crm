<?php

namespace ModularCore\Bootstrap;

use Exception;
use Dotenv\Dotenv;

/**
 * Requirement 2: Implement Environment Variable Configuration
 */
class ConfigLoader
{
    private static $instance = null;
    private $config = [];

    private function __construct()
    {
        // Load .env if it exists (Requirement 2.8)
        if (file_exists(base_path('.env'))) {
            $dotenv = Dotenv::createImmutable(base_path());
            $dotenv->load();
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Requirement 2.5: Descriptive error for missing variables
     */
    public function getRequired(string $key): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false || $value === null || $value === '') {
            throw new Exception("CRITICAL SECURITY ERROR: Missing required environment variable [{$key}]. System cannot boot.");
        }

        return (string) $value;
    }

    public function get(string $key, $default = null)
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Requirement 2.1: Database credentials
     */
    public function getDatabaseConfig(): array
    {
        return [
            'host'     => $this->getRequired('DB_HOST'),
            'port'     => $this->get('DB_PORT', '5432'),
            'database' => $this->getRequired('DB_NAME'),
            'username' => $this->getRequired('DB_USER'),
            'password' => $this->getRequired('DB_PASSWORD'),
        ];
    }

    /**
     * Requirement 2.3: Encryption keys
     */
    public function getEncryptionConfig(): array
    {
        return [
            'master_key' => $this->getRequired('APP_MASTER_KEY'),
            'cipher'     => 'aes-256-gcm'
        ];
    }

    /**
     * Requirement 2.2: API keys
     */
    public function getApiKeys(): array
    {
        return [
            'openai'    => $this->get('OPENAI_API_KEY'),
            'anthropic' => $this->get('ANTHROPIC_API_KEY'),
            'stripe'    => $this->get('STRIPE_SECRET_KEY'),
            'zatca'     => $this->get('ZATCA_API_KEY')
        ];
    }
}

// Global Helper
if (!function_exists('config_load')) {
    function config_load() {
        return ConfigLoader::getInstance();
    }
}

if (!function_exists('base_path')) {
    function base_path($path = '') {
        return __DIR__ . '/../../' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}
