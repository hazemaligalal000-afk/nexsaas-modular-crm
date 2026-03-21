<?php
/**
 * Partners/APIKeyService.php
 * 
 * CORE → ADVANCED: Secure Public API Key Registry
 */

declare(strict_types=1);

namespace Modules\Partners;

use Core\BaseService;

class APIKeyService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Generate a new secure API Key for a partner
     * Rule: Key is a cryptographically secure 64-char string
     */
    public function generateKey(int $partnerId, string $label): string
    {
        $key = 'pk_' . bin2hex(random_bytes(32));
        $secret = bin2hex(random_bytes(32));

        $this->db->Execute(
            "INSERT INTO partner_api_keys (partner_id, api_key, api_secret, label, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$partnerId, $key, hash('sha256', $secret), $label]
        );

        // We only return the secret once (Show to user once)
        return $key . '.' . $secret;
    }

    /**
     * Authenticate a request using an API Key
     */
    public function authenticate(string $apiKey, string $apiSecret): bool
    {
        $sql = "SELECT id FROM partner_api_keys 
                WHERE api_key = ? AND api_secret = ? AND is_active = TRUE";
        
        $valid = $this->db->GetOne($sql, [$apiKey, hash('sha256', $apiSecret)]);

        return (bool)$valid;
    }
}
