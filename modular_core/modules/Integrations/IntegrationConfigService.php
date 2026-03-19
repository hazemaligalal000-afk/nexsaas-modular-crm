<?php
/**
 * Integrations/IntegrationConfigService.php
 *
 * CRUD for integration_configs — stores encrypted credentials per tenant/platform.
 */

declare(strict_types=1);

namespace Integrations;

use Core\BaseService;

class IntegrationConfigService extends BaseService
{
    public function getConfig(string $tenantId, string $platform): ?array
    {
        $rs = $this->db->Execute(
            'SELECT * FROM integration_configs WHERE tenant_id = ? AND platform = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1',
            [$tenantId, $platform]
        );
        if ($rs === false || $rs->EOF) {
            return null;
        }
        $row = $rs->fields;
        // Decrypt credentials
        $row['credentials'] = $this->decryptCredentials($row['credentials']);
        return $row;
    }

    public function saveConfig(string $tenantId, string $companyCode, string $platform, array $credentials, array $settings = []): int
    {
        $encrypted = $this->encryptCredentials($credentials);
        $now       = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Upsert
        $existing = $this->db->Execute(
            'SELECT id FROM integration_configs WHERE tenant_id = ? AND platform = ?',
            [$tenantId, $platform]
        );

        if ($existing !== false && !$existing->EOF) {
            $id = (int)$existing->fields['id'];
            $this->db->Execute(
                'UPDATE integration_configs SET credentials = ?, settings = ?, updated_at = ? WHERE id = ?',
                [$encrypted, json_encode($settings), $now, $id]
            );
            return $id;
        }

        $rs = $this->db->Execute(
            'INSERT INTO integration_configs (tenant_id, company_code, platform, credentials, settings, is_active, created_at, updated_at) VALUES (?,?,?,?,?,1,?,?) RETURNING id',
            [$tenantId, $companyCode, $platform, $encrypted, json_encode($settings), $now, $now]
        );

        return (!$rs->EOF) ? (int)$rs->fields['id'] : (int)$this->db->Insert_ID();
    }

    public function listActive(string $tenantId): array
    {
        $rs = $this->db->Execute(
            'SELECT id, platform, company_code, is_active, settings, created_at FROM integration_configs WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY platform',
            [$tenantId]
        );
        $rows = [];
        while ($rs && !$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }
        return $rows;
    }

    public function toggleActive(string $tenantId, string $platform, bool $active): bool
    {
        $rs = $this->db->Execute(
            'UPDATE integration_configs SET is_active = ?, updated_at = NOW() WHERE tenant_id = ? AND platform = ?',
            [$active ? 1 : 0, $tenantId, $platform]
        );
        return $rs !== false;
    }

    private function encryptCredentials(array $credentials): string
    {
        $json = json_encode($credentials);
        $key  = $_ENV['ENCRYPTION_KEY'] ?? str_repeat('0', 32);
        $iv   = random_bytes(16);
        $enc  = openssl_encrypt($json, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $enc);
    }

    private function decryptCredentials(string $encrypted): array
    {
        try {
            $key  = $_ENV['ENCRYPTION_KEY'] ?? str_repeat('0', 32);
            $data = base64_decode($encrypted);
            $iv   = substr($data, 0, 16);
            $enc  = substr($data, 16);
            $json = openssl_decrypt($enc, 'AES-256-CBC', $key, 0, $iv);
            return json_decode((string)$json, true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }
}
