<?php
namespace Modules\Accounting\FX;

/**
 * FXRevaluationTask: Celery task for FX revaluation
 * Task 31.4
 * Requirements: 47.7, 47.8
 * 
 * This task should be scheduled to run:
 * 1. At period close: perform unrealized revaluation
 * 2. At period open: auto-reverse prior period revaluation
 */
class FXRevaluationTask {
    
    /**
     * Perform period-close FX revaluation for all companies
     * 
     * @param string $finPeriod Financial period (YYYYMM)
     * @param string $closingDate Closing date (YYYY-MM-DD)
     * @return array Results per company
     */
    public static function performPeriodCloseRevaluation(string $finPeriod, string $closingDate): array {
        $companies = ['01', '02', '03', '04', '05', '06'];
        $results = [];
        
        foreach ($companies as $companyCode) {
            try {
                $fxService = new FXService();
                $result = $fxService->performUnrealizedRevaluation($companyCode, $finPeriod, $closingDate);
                
                $results[$companyCode] = [
                    'success' => true,
                    'entries_count' => count($result['revaluation_entries']),
                    'total_adjustment' => $result['total_adjustment']
                ];
                
                error_log("FX Revaluation completed for Company {$companyCode}: " . 
                         count($result['revaluation_entries']) . " entries, " .
                         "total adjustment: {$result['total_adjustment']} EGP");
                
            } catch (\Exception $e) {
                $results[$companyCode] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                
                error_log("FX Revaluation failed for Company {$companyCode}: " . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * Auto-reverse revaluation entries at period open
     * 
     * @param string $priorPeriod Prior financial period (YYYYMM)
     * @param string $newPeriod New financial period (YYYYMM)
     * @return array Results per company
     */
    public static function performPeriodOpenReversal(string $priorPeriod, string $newPeriod): array {
        $companies = ['01', '02', '03', '04', '05', '06'];
        $results = [];
        
        foreach ($companies as $companyCode) {
            try {
                $fxService = new FXService();
                $count = $fxService->autoReverseRevaluation($companyCode, $priorPeriod, $newPeriod);
                
                $results[$companyCode] = [
                    'success' => true,
                    'entries_reversed' => $count
                ];
                
                error_log("FX Revaluation reversal completed for Company {$companyCode}: {$count} entries reversed");
                
            } catch (\Exception $e) {
                $results[$companyCode] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                
                error_log("FX Revaluation reversal failed for Company {$companyCode}: " . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * Refresh exchange rates from Central Bank of Egypt API
     * Scheduled to run daily at midnight
     * Requirement 47.5
     * 
     * @param string $date Date to fetch rates for (defaults to today)
     * @return array Results per currency
     */
    public static function refreshExchangeRates(string $date = null): array {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        $currencies = ['02', '03', '04', '05', '06']; // USD, AED, SAR, EUR, GBP
        $results = [];
        
        foreach ($currencies as $currencyCode) {
            try {
                $fxService = new FXService();
                
                // Check if auto-fetch is enabled
                // This would be checked per tenant in production
                $rate = $fxService->getRateForDate($currencyCode, $date);
                
                $results[$currencyCode] = [
                    'success' => true,
                    'rate' => $rate,
                    'date' => $date
                ];
                
                error_log("Exchange rate refreshed for {$currencyCode}: {$rate} on {$date}");
                
            } catch (\Exception $e) {
                $results[$currencyCode] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                
                error_log("Exchange rate refresh failed for {$currencyCode}: " . $e->getMessage());
            }
        }
        
        return $results;
    }
}
