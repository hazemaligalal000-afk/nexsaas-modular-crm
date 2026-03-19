<?php
namespace Modules\Accounting\Statements;

use Core\BaseService;
use Core\Database;

/**
 * CashFlowStatementService: Direct Method Cash Generation
 * Batch J - Task 38.3
 */
class CashFlowStatementService extends BaseService {

    /**
     * Implement Cash Flow Statement (direct method from bank account movements) (Req 54.4)
     */
    public function generateDirectCashFlow(string $startDate, string $endDate) {
        $db = Database::getInstance();
        
        // Maps 'transaction_type' from bank_transactions directly natively, distinguishing operating vs financing vs investing
        // Assuming metadata labels or generic categorizations appended during the transaction import
        
        $sql = "
            SELECT transaction_type, 
                   categorization, -- 'Operating', 'Investing', 'Financing'
                   SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as cash_inflows,
                   SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END) as cash_outflows
            FROM bank_transactions
            WHERE tenant_id = ? AND company_code = ? AND transaction_date BETWEEN ? AND ?
            GROUP BY transaction_type, categorization
        ";
        
        $movements = $db->query($sql, [$this->tenantId, $this->companyCode, $startDate, $endDate]);
        
        $operatingNet = 0.00;
        $investingNet = 0.00;
        $financingNet = 0.00;
        
        foreach ($movements as $m) {
            $net = $m['cash_inflows'] + $m['cash_outflows']; // outflows are natively negative
            if ($m['categorization'] === 'Operating') $operatingNet += $net;
            if ($m['categorization'] === 'Investing') $investingNet += $net;
            if ($m['categorization'] === 'Financing') $financingNet += $net;
        }
        
        $totalNetIncrease = $operatingNet + $investingNet + $financingNet;
        
        return [
            'period_start' => $startDate,
            'period_end' => $endDate,
            'cash_flows' => [
                'operating_activities' => $operatingNet,
                'investing_activities' => $investingNet,
                'financing_activities' => $financingNet
            ],
            'net_increase_in_cash' => $totalNetIncrease
        ];
    }
}
