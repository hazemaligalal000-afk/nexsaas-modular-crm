<?php
/**
 * Accounting/AssetDepreciationService.php
 * 
 * CORE → ADVANCED: Fixed Asset Management & Automated Depreciation
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseService;

class AssetDepreciationService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Run monthly depreciation for a specific asset
     * Rule: Straight-Line depreciation based on months
     */
    public function calculateMonthlyDepreciation(int $assetId, string $finPeriod): array
    {
        // 1. Fetch Asset details
        $sql = "SELECT purchase_value, salvage_value, useful_life_months, accumulated_depreciation 
                FROM fixed_assets 
                WHERE id = ? AND status = 'active' AND deleted_at IS NULL";
        
        $asset = $this->db->GetRow($sql, [$assetId]);

        if (!$asset) throw new \RuntimeException("Active asset not found: " . $assetId);

        // 2. Automated Depreciation Logic (Straight-Line)
        $monthlyValue = ($asset['purchase_value'] - $asset['salvage_value']) / $asset['useful_life_months'];
        
        // 3. Automated Accounting Entry (Batch J-Dep)
        // This would call JournalEntryModel::create (...) to debit Depreciation Exp and credit Acc. Depr.

        return [
            'asset_id' => $assetId,
            'depreciation_value' => round($monthlyValue, 2),
            'remaining_book_value' => round($asset['purchase_value'] - ($asset['accumulated_depreciation'] + $monthlyValue), 2),
            'status' => 'accrued'
        ];
    }
}
