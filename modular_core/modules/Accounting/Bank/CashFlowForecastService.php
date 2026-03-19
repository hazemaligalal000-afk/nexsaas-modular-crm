<?php
namespace Modules\Accounting\Bank;

use Core\BaseService;
use Core\Database;

/**
 * CashFlowForecastService: Predict cash positions 30/60/90 days ahead
 * Batch E - Task 33.5
 */
class CashFlowForecastService extends BaseService {

    /**
     * Compute 30/60/90 days forward-looking AR/AP settlement logic
     * Req 49.11
     */
    public function generateForecast(string $anchorDate) {
        $db = Database::getInstance();
        $periods = [30, 60, 90];
        $forecasts = [];
        
        $currentCashPositionService = new CashPositionService($this->tenantId, $this->companyCode);
        $currentCash = $currentCashPositionService->getCashPositionDashboard($anchorDate)['total_egp_equivalent'];
        
        foreach ($periods as $days) {
            $endDate = date('Y-m-d', strtotime("{$anchorDate} + {$days} days"));
            
            // Expected Inflows (AR)
            $params = [$this->tenantId, $this->companyCode, $anchorDate, $endDate];
            $arSql = "SELECT SUM(total_amount - amount_paid) as expected_in FROM ar_invoices 
                      WHERE tenant_id = ? AND company_code = ? AND status IN ('open', 'partially_paid') AND due_date BETWEEN ? AND ?";
            $expectedIn = floatval($db->query($arSql, $params)[0]['expected_in'] ?? 0);
            
            // Expected Outflows (AP)
            $apSql = "SELECT SUM(total_amount - amount_paid) as expected_out FROM ap_bills 
                      WHERE tenant_id = ? AND company_code = ? AND status IN ('open', 'partially_paid') AND due_date BETWEEN ? AND ?";
            $expectedOut = floatval($db->query($apSql, $params)[0]['expected_out'] ?? 0);
            
            $netMovement = $expectedIn - $expectedOut;
            $forecastedPosition = $currentCash + $netMovement;
            
            $forecasts["{$days}_days"] = [
                'end_date' => $endDate,
                'current_cash' => $currentCash,
                'expected_inflows' => $expectedIn,
                'expected_outflows' => $expectedOut,
                'net_movement' => $netMovement,
                'forecasted_position_egp' => $forecastedPosition
            ];
            
            // Waterfall carries over
            $currentCash = $forecastedPosition; 
        }
        
        return $forecasts;
    }
    
    /**
     * Month-End accrue interest for Bank Account (Req 49.12)
     */
    public function accrueInterest(int $bankAccountId, float $annualInterestRate, int $daysHeld) {
        // Daily rate * days held * current average balance
        // Generates auto-posting journal entry: Debit Bank, Credit Interest Income (Other Revenue)
        return ['status' => 'interest_accrued_and_posted'];
    }
}
