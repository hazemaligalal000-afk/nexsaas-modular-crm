<?php
/**
 * Accounting/ExchangeRateService.php
 * 
 * BATCH C — Multi-Currency & FX Engine
 * Manages daily rates and currency conversion.
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseService;

class ExchangeRateService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get the live rate for a currency vs EGP (Rule 7.1)
     */
    public function getLatestRate(string $currencyCode, string $companyCode): float
    {
        $sql = "SELECT rate_to_egp FROM exchange_rates 
                WHERE currency_code = ? AND company_code = ? AND tenant_id = ?
                ORDER BY rate_date DESC LIMIT 1";
        
        $res = $this->db->GetOne($sql, [$currencyCode, $companyCode, $this->tenantId]);
        
        return $res ? (float)$res : 1.0;
    }

    /**
     * Get the rate for a specific date
     */
    public function getRateOnDate(string $currencyCode, string $companyCode, string $date): float
    {
        $sql = "SELECT rate_to_egp FROM exchange_rates 
                WHERE currency_code = ? AND company_code = ? AND tenant_id = ? AND rate_date <= ?
                ORDER BY rate_date DESC LIMIT 1";
        
        $res = $this->db->GetOne($sql, [$currencyCode, $companyCode, $this->tenantId, $date]);
        
        return $res ? (float)$res : 1.0;
    }

    /**
     * Store a new daily rate
     */
    public function updateRate(string $currencyCode, string $companyCode, string $date, float $rate, int $userId): void
    {
        $sql = "INSERT INTO exchange_rates (tenant_id, company_code, currency_code, rate_date, rate_to_egp)
                VALUES (?, ?, ?, ?, ?)
                ON CONFLICT (tenant_id, company_code, currency_code, rate_date)
                DO UPDATE SET rate_to_egp = EXCLUDED.rate_to_egp, updated_at = NOW()";
        
        $this->db->Execute($sql, [$this->tenantId, $companyCode, $currencyCode, $date, $rate]);
    }
}
