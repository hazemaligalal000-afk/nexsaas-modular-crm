<?php
/**
 * Accounting/CompanyModel.php
 * 
 * Company Master Model - Multi-company group management
 * Based on: Company_Code.xlsx
 * 
 * BATCH A — Chart of Accounts Foundation
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseModel;

class CompanyModel extends BaseModel
{
    protected string $table = 'companies';

    /**
     * Get all active companies for the current tenant
     * 
     * @return array
     */
    public function getAllCompanies(): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = TRUE ORDER BY code";
        return $this->scopeQuery($sql);
    }

    /**
     * Get company by code
     * 
     * @param string $code Two-digit company code (01-06)
     * @return array|null
     */
    public function getByCode(string $code): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE code = ?";
        $rows = $this->scopeQuery($sql, [$code]);
        return $rows[0] ?? null;
    }

    /**
     * Create a new company
     * 
     * @param array $data Company data
     * @return int Company ID
     */
    public function createCompany(array $data): int
    {
        // Validate company code format
        if (!preg_match('/^0[1-6]$/', $data['code'])) {
            throw new \InvalidArgumentException('Company code must be 01-06');
        }

        // Check for duplicate code
        $existing = $this->getByCode($data['code']);
        if ($existing !== null) {
            throw new \RuntimeException("Company code {$data['code']} already exists");
        }

        return $this->insert($data);
    }

    /**
     * Update company details
     * 
     * @param int $id Company ID
     * @param array $data Updated data
     * @return bool
     */
    public function updateCompany(int $id, array $data): bool
    {
        // Prevent code changes
        unset($data['code']);
        
        return $this->update($id, $data);
    }

    /**
     * Check if company has E-Invoice active
     * 
     * @param string $companyCode
     * @return bool
     */
    public function isEInvoiceActive(string $companyCode): bool
    {
        $company = $this->getByCode($companyCode);
        return $company !== null && ($company['e_invoice_active'] ?? false);
    }

    /**
     * Check if company is VAT registered
     * 
     * @param string $companyCode
     * @return bool
     */
    public function isVATRegistered(string $companyCode): bool
    {
        $company = $this->getByCode($companyCode);
        return $company !== null && ($company['vat_registered'] ?? false);
    }

    /**
     * Get companies with expiring tax cards (within N days)
     * 
     * @param int $daysAhead Number of days to look ahead
     * @return array
     */
    public function getExpiringTaxCards(int $daysAhead = 90): array
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE tax_card_expiry IS NOT NULL
            AND tax_card_expiry BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '{$daysAhead} days'
            ORDER BY tax_card_expiry
        ";
        return $this->scopeQuery($sql);
    }

    /**
     * Get companies with expiring commercial registrations (within N days)
     * 
     * @param int $daysAhead Number of days to look ahead
     * @return array
     */
    public function getExpiringCommercialRegs(int $daysAhead = 90): array
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE commercial_reg_expiry IS NOT NULL
            AND commercial_reg_expiry BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '{$daysAhead} days'
            ORDER BY commercial_reg_expiry
        ";
        return $this->scopeQuery($sql);
    }
}
