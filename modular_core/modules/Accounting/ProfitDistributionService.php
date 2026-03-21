<?php
/**
 * Accounting/ProfitDistributionService.php
 * 
 * BATCH I — Partner Profit Distribution (Monthly)
 * Calculates and distributes net profits to company partners.
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseService;

class ProfitDistributionService extends BaseService
{
    private StatementService $statementService;
    private JournalEntryService $jeService;
    private $db;

    public function __construct($db, StatementService $statementService, JournalEntryService $jeService)
    {
        $this->db = $db;
        $this->statementService = $statementService;
        $this->jeService = $jeService;
    }

    /**
     * Distribute monthly profits to partners
     * 
     * Rule: Based on ownership_pct in partners table
     */
    public function distributeMonthlyProfit(string $tenantId, string $companyCode, string $finPeriod, int $userId): array
    {
        // 1. Get net profit for the period
        $pl = $this->statementService->getProfitLoss($tenantId, $companyCode, $finPeriod, $finPeriod);
        $netProfit = $pl['net_profit'];

        if ($netProfit <= 0) {
            return [
                'distributed' => false,
                'message' => 'No profit to distribute for ' . $finPeriod,
                'net_profit' => $netProfit
            ];
        }

        // 2. Get partners and their percentages
        $sql = "SELECT partner_code, partner_name, ownership_pct FROM partners 
                WHERE tenant_id = ? AND company_code = ? AND deleted_at IS NULL";
        $partners = $this->db->GetAll($sql, [$tenantId, $companyCode]);

        if (empty($partners)) {
            throw new \RuntimeException("No partners found for company: " . $companyCode);
        }

        // 3. Prepare Journal Entry lines
        $lines = [];
        
        // Debit: Undistributed Profit / Retained Earnings Account (Configurable, using 3.1.1 for now)
        $lines[] = [
            'account_code' => '3.1.1', // Retained Earnings / Current Period Profit
            'dr_value' => $netProfit,
            'cr_value' => 0,
            'line_desc' => 'MONTHLY PROFIT DISTRIBUTION - ' . $finPeriod,
        ];

        // Credits: Partner Current Accounts
        foreach ($partners as $partner) {
            $share = round(($netProfit * $partner['ownership_pct']) / 100, 2);
            $lines[] = [
                'account_code' => '2.1.5', // Partner Current Accounts (Liability)
                'dr_value' => 0,
                'cr_value' => $share,
                'partner_no' => $partner['partner_code'],
                'line_desc' => 'PROFIT SHARE (' . $partner['ownership_pct'] . '%): ' . $partner['partner_name'],
            ];
        }

        // 4. Create Voucher 999 (Settlement Section 991)
        $header = [
            'company_code' => $companyCode,
            'voucher_code' => '999',
            'section_code' => '991',
            'voucher_date' => date('Y-m-d'),
            'fin_period' => $finPeriod,
            'currency_code' => '01', // Base Currency
            'exchange_rate' => 1.0,
            'description' => 'AUTO-GENERATED: PARTNER PROFIT DISTRIBUTION - ' . $finPeriod,
            'status' => 'draft'
        ];

        $id = $this->jeService->create($header, $lines, $userId);

        return [
            'distributed' => true,
            'je_id' => $id,
            'net_profit' => $netProfit,
            'partner_count' => count($partners)
        ];
    }
}
