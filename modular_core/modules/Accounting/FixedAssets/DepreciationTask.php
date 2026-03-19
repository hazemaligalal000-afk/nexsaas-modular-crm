<?php
namespace Modules\Accounting\FixedAssets;

use Core\Database;

/**
 * DepreciationTask: Monthly Depreciation Celery Task (Batch G)
 * Task 35.3
 * Requirement: 51.4
 * 
 * This task should be scheduled to run monthly to calculate and post
 * depreciation journal entries for all active assets across all companies.
 */
class DepreciationTask {
    
    /**
     * Run monthly depreciation for all companies
     * 
     * @param string $finPeriod Financial period (YYYYMM)
     * @return array Results per company
     */
    public static function runMonthlyDepreciation(string $finPeriod): array {
        $companies = ['01', '02', '03', '04', '05', '06'];
        $results = [];
        
        foreach ($companies as $companyCode) {
            try {
                $result = self::runDepreciationForCompany($companyCode, $finPeriod);
                $results[$companyCode] = $result;
                
                error_log("Depreciation completed for Company {$companyCode}: " . 
                         "{$result['assets_processed']} assets, " .
                         "{$result['total_depreciation']} EGP");
                
            } catch (\Exception $e) {
                $results[$companyCode] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                
                error_log("Depreciation failed for Company {$companyCode}: " . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * Run depreciation for a specific company
     * 
     * @param string $companyCode Company code
     * @param string $finPeriod Financial period
     * @return array Result summary
     */
    private static function runDepreciationForCompany(string $companyCode, string $finPeriod): array {
        $db = Database::getInstance();
        
        // Get all active assets for this company
        $sql = "SELECT id, asset_code, asset_name_en, net_book_value, salvage_value
                FROM fixed_assets
                WHERE company_code = ?
                AND status = 'active'
                AND net_book_value > salvage_value
                AND deleted_at IS NULL
                ORDER BY asset_code";
        
        // Note: In production, this would need proper tenant_id filtering
        // For now, we'll assume a single tenant context
        $assets = $db->query($sql, [$companyCode]);
        
        $assetsProcessed = 0;
        $totalDepreciation = 0;
        $journalEntries = [];
        
        foreach ($assets as $asset) {
            try {
                // Create service instance (would need proper tenant context in production)
                $assetService = new FixedAssetService();
                
                // Post depreciation
                $jeId = $assetService->postDepreciation($asset['id'], $finPeriod);
                
                if ($jeId) {
                    $assetsProcessed++;
                    $journalEntries[] = $jeId;
                    
                    // Get depreciation amount from the journal entry
                    $depreciation = self::getDepreciationAmount($jeId);
                    $totalDepreciation += $depreciation;
                }
                
            } catch (\Exception $e) {
                error_log("Failed to depreciate asset {$asset['asset_code']}: " . $e->getMessage());
            }
        }
        
        return [
            'success' => true,
            'company_code' => $companyCode,
            'fin_period' => $finPeriod,
            'total_assets' => count($assets),
            'assets_processed' => $assetsProcessed,
            'total_depreciation' => round($totalDepreciation, 2),
            'journal_entries' => $journalEntries
        ];
    }

    /**
     * Get depreciation amount from journal entry
     * 
     * @param int $jeId Journal entry ID
     * @return float Depreciation amount
     */
    private static function getDepreciationAmount(int $jeId): float {
        $db = Database::getInstance();
        
        $sql = "SELECT debit
                FROM journal_entry_lines
                WHERE journal_entry_id = ?
                AND account_code = '5010'
                LIMIT 1";
        
        $result = $db->query($sql, [$jeId]);
        
        return !empty($result) ? (float)$result[0]['debit'] : 0;
    }

    /**
     * Run depreciation for a specific asset (for testing/manual runs)
     * 
     * @param int $assetId Asset ID
     * @param string $finPeriod Financial period
     * @return array Result
     */
    public static function runDepreciationForAsset(int $assetId, string $finPeriod): array {
        try {
            $assetService = new FixedAssetService();
            $jeId = $assetService->postDepreciation($assetId, $finPeriod);
            
            if ($jeId) {
                $depreciation = self::getDepreciationAmount($jeId);
                
                return [
                    'success' => true,
                    'asset_id' => $assetId,
                    'fin_period' => $finPeriod,
                    'depreciation_amount' => $depreciation,
                    'journal_entry_id' => $jeId
                ];
            } else {
                return [
                    'success' => false,
                    'asset_id' => $assetId,
                    'message' => 'No depreciation posted (asset fully depreciated or inactive)'
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'asset_id' => $assetId,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate depreciation summary report
     * 
     * @param string $finPeriod Financial period
     * @return array Summary report
     */
    public static function generateDepreciationSummary(string $finPeriod): array {
        $db = Database::getInstance();
        
        // Get depreciation summary by company and category
        $sql = "SELECT 
                    fa.company_code,
                    fa.asset_category,
                    COUNT(DISTINCT fa.id) as asset_count,
                    SUM(jel.debit) as total_depreciation
                FROM fixed_assets fa
                JOIN journal_entry_lines jel ON jel.asset_no = fa.asset_code
                JOIN journal_entries je ON je.id = jel.journal_entry_id
                WHERE je.fin_period = ?
                AND jel.account_code = '5010'
                AND je.description LIKE 'Monthly Depreciation:%'
                AND fa.deleted_at IS NULL
                GROUP BY fa.company_code, fa.asset_category
                ORDER BY fa.company_code, fa.asset_category";
        
        $summary = $db->query($sql, [$finPeriod]);
        
        // Calculate grand totals
        $grandTotal = 0;
        $totalAssets = 0;
        
        foreach ($summary as $row) {
            $grandTotal += $row['total_depreciation'];
            $totalAssets += $row['asset_count'];
        }
        
        return [
            'fin_period' => $finPeriod,
            'summary_by_company_category' => $summary,
            'grand_totals' => [
                'total_assets' => $totalAssets,
                'total_depreciation' => round($grandTotal, 2)
            ]
        ];
    }
}
