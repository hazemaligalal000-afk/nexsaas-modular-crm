<?php
/**
 * EmailDesigner/BrandSettingsModel.php
 *
 * CRUD for email_brand_settings — per-company branding.
 */

declare(strict_types=1);

namespace EmailDesigner;

class BrandSettingsModel
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getForCompany(string $tenantId, string $companyCode): array
    {
        $rs = $this->db->Execute(
            'SELECT * FROM email_brand_settings WHERE tenant_id = ? AND company_code = ?',
            [$tenantId, $companyCode]
        );

        if ($rs === false || $rs->EOF) {
            return $this->getSystemDefaults($tenantId, $companyCode);
        }

        $row = $rs->fields;

        // Decrypt SMTP password
        if ($row['smtp_password_enc']) {
            $row['smtp_password'] = $this->decrypt($row['smtp_password_enc']);
            unset($row['smtp_password_enc']);
        }

        return $row;
    }

    public function save(string $tenantId, string $companyCode, array $data): int
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Encrypt sensitive fields
        if (isset($data['smtp_password'])) {
            $data['smtp_password_enc'] = $this->encrypt($data['smtp_password']);
            unset($data['smtp_password']);
        }

        $existing = $this->db->Execute(
            'SELECT id FROM email_brand_settings WHERE tenant_id = ? AND company_code = ?',
            [$tenantId, $companyCode]
        );

        if ($existing !== false && !$existing->EOF) {
            $id = (int)$existing->fields['id'];
            $this->db->Execute(
                'UPDATE email_brand_settings SET ' . $this->buildUpdateSQL($data) . ', updated_at = ? WHERE id = ?',
                array_merge(array_values($data), [$now, $id])
            );
            return $id;
        }

        $data['tenant_id']    = $tenantId;
        $data['company_code'] = $companyCode;
        $data['created_at']   = $now;
        $data['updated_at']   = $now;

        $rs = $this->db->Execute(
            'INSERT INTO email_brand_settings (' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ') RETURNING id',
            array_values($data)
        );

        return (!$rs->EOF) ? (int)$rs->fields['id'] : (int)$this->db->Insert_ID();
    }

    private function getSystemDefaults(string $tenantId, string $companyCode): array
    {
        return [
            'tenant_id'         => $tenantId,
            'company_code'      => $companyCode,
            'company_name_en'   => 'NexSaaS',
            'color_primary'     => '#1E3A5F',
            'color_secondary'   => '#2E86C1',
            'font_family'       => 'Inter, Arial, sans-serif',
            'sender_name_en'    => 'NexSaaS',
            'sender_email'      => 'noreply@nexsaas.com',
            'smtp_provider'     => 'smtp',
        ];
    }

    private function buildUpdateSQL(array $data): string
    {
        $parts = [];
        foreach (array_keys($data) as $key) {
            $parts[] = "{$key} = ?";
        }
        return implode(', ', $parts);
    }

    private function encrypt(string $value): string
    {
        $key = $_ENV['ENCRYPTION_KEY'] ?? str_repeat('0', 32);
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $enc);
    }

    private function decrypt(string $encrypted): string
    {
        try {
            $key  = $_ENV['ENCRYPTION_KEY'] ?? str_repeat('0', 32);
            $data = base64_decode($encrypted);
            $iv   = substr($data, 0, 16);
            $enc  = substr($data, 16);
            return (string)openssl_decrypt($enc, 'AES-256-CBC', $key, 0, $iv);
        } catch (\Throwable) {
            return '';
        }
    }
}
