<?php
/**
 * Accounting/CurrencyModel.php
 * 
 * Currency Master Model - Multi-currency operations
 * Based on: Currency_Code.xlsx
 * 
 * BATCH C — Multi-Currency & Exchange Rate Engine
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseModel;

class CurrencyModel extends BaseModel
{
    protected string $table = 'currencies';

    /**
     * Get all active currencies
     * 
     * @return array
     */
    public function getAllCurrencies(): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY code";
        return $this->scopeQuery($sql);
    }

    /**
     * Get currency by code
     * 
     * @param string $code Two-digit currency code (01-06)
     * @return array|null
     */
    public function getByCode(string $code): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE code = ?";
        $rows = $this->scopeQuery($sql, [$code]);
        return $rows[0] ?? null;
    }

    /**
     * Get currency by ISO code
     * 
     * @param string $isoCode Three-letter ISO code (EGP, USD, etc.)
     * @return array|null
     */
    public function getByISOCode(string $isoCode): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE iso_code = ?";
        $rows = $this->scopeQuery($sql, [strtoupper($isoCode)]);
        return $rows[0] ?? null;
    }

    /**
     * Get base currency (typically EGP)
     * 
     * @return array|null
     */
    public function getBaseCurrency(): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE is_base_currency = TRUE LIMIT 1";
        $rows = $this->scopeQuery($sql);
        return $rows[0] ?? null;
    }

    /**
     * Create a new currency
     * 
     * @param array $data Currency data
     * @return int Currency ID
     */
    public function createCurrency(array $data): int
    {
        // Validate currency code format
        if (!preg_match('/^0[1-6]$/', $data['code'])) {
            throw new \InvalidArgumentException('Currency code must be 01-06');
        }

        // Check for duplicate code
        $existing = $this->getByCode($data['code']);
        if ($existing !== null) {
            throw new \RuntimeException("Currency code {$data['code']} already exists");
        }

        // Check for duplicate ISO code
        $existingISO = $this->getByISOCode($data['iso_code']);
        if ($existingISO !== null) {
            throw new \RuntimeException("Currency ISO code {$data['iso_code']} already exists");
        }

        return $this->insert($data);
    }

    /**
     * Update currency details
     * 
     * @param int $id Currency ID
     * @param array $data Updated data
     * @return bool
     */
    public function updateCurrency(int $id, array $data): bool
    {
        // Prevent code and ISO code changes
        unset($data['code'], $data['iso_code']);
        
        return $this->update($id, $data);
    }
}
