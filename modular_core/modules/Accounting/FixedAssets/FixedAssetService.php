<?php
namespace Modules\Accounting\FixedAssets;

use Core\BaseService;
use Core\Database;

/**
 * FixedAssetService: Fixed Asset Lifecycle Management (Batch G)
 * Task 35.2, 35.3, 35.4, 35.5, 35.6
 * Requirements: 51.1 - 51.10
 */
class FixedAssetService extends BaseService {
    
    /**
     * Acquire a new fixed asset
     * Req 51.2
     * 
     * @param array $data Asset data
     * @return int Asset ID
     */
    public function acquireAsset(array $data): int {
        $db = Database::getInstance();
        
        // Validate required fields
        $required = ['company_code', 'asset_code', 'asset_name_en', 'asset_category', 
                     'account_code', 'purchase_date', 'purchase_cost'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field {$field} is required");
            }
        }
        
        // Calculate net book value
        $netBookValue = $data['purchase_cost'] - ($data['salvage_value'] ?? 0);
        
        // Insert asset
        $assetId = $db->insert('fixed_assets', [
            'tenant_id' => $this->tenantId,
            'company_code' => $data['company_code'],
            'asset_code' => $data['asset_code'],
            'asset_name_en' => $data['asset_name_en'],
            'asset_name_ar' => $data['asset_name_ar'] ?? null,
            'asset_category' => $data['asset_category'],
            'account_code' => $data['account_code'],
            'purchase_date' => $data['purchase_date'],
            'purchase_cost' => $data['purchase_cost'],
            'currency_code' => $data['currency_code'] ?? '01',
            'salvage_value' => $data['salvage_value'] ?? 0,
            'useful_life_years' => $data['useful_life_years'] ?? 5,
            'depreciation_method' => $data['depreciation_method'] ?? 'straight_line',
            'accumulated_depreciation' => 0,
            'net_book_value' => $netBookValue,
            'status' => 'active',
            'created_by' => $this->userId ?? 'system',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Post acquisition journal entry
        $this->postAcquisitionEntry($assetId, $data);
        
        return $assetId;
    }

    /**
     * Post asset acquisition journal entry
     * 
     * @param int $assetId Asset ID
     * @param array $data Asset data
     * @return int Journal entry ID
     */
    private function postAcquisitionEntry(int $assetId, array $data): int {
        $db = Database::getInstance();
        
        // Create journal entry
        $jeId = $db->insert('journal_entries', [
            'tenant_id' => $this->tenantId,
            'company_code' => $data['company_code'],
            'entry_date' => $data['purchase_date'],
            'description' => "Asset Acquisition: {$data['asset_name_en']}",
            'status' => 'posted',
            'created_by' => $this->userId ?? 'system',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Debit: Asset account
        $db->insert('journal_entry_lines', [
            'journal_entry_id' => $jeId,
            'account_code' => $data['account_code'],
            'debit' => $data['purchase_cost'],
            'credit' => 0,
            'asset_no' => $data['asset_code'],
            'description' => "Asset Acquisition"
        ]);
        
        // Credit: Cash/Payable (simplified - would be more complex in production)
        $db->insert('journal_entry_lines', [
            'journal_entry_id' => $jeId,
            'account_code' => '2010', // Accounts Payable
            'debit' => 0,
            'credit' => $data['purchase_cost'],
            'description' => "Asset Purchase"
        ]);
        
        return $jeId;
    }

    /**
     * Calculate monthly depreciation for an asset
     * Req 51.3, 51.4
     * 
     * @param int $assetId Asset ID
     * @param string $depreciationDate Date for depreciation
     * @return array Depreciation details
     */
    public function calculateDepreciation(int $assetId, string $depreciationDate): array {
        $db = Database::getInstance();
        
        // Get asset details
        $asset = $db->query(
            "SELECT * FROM fixed_assets WHERE id = ? AND tenant_id = ?",
            [$assetId, $this->tenantId]
        );
        
        if (empty($asset)) {
            throw new \RuntimeException("Asset not found");
        }
        
        $asset = $asset[0];
        
        // Check if asset is active
        if ($asset['status'] !== 'active') {
            return [
                'depreciation_amount' => 0,
                'reason' => 'Asset is not active'
            ];
        }
        
        // Check if fully depreciated
        if ($asset['net_book_value'] <= $asset['salvage_value']) {
            return [
                'depreciation_amount' => 0,
                'reason' => 'Asset is fully depreciated'
            ];
        }
        
        $depreciableAmount = $asset['purchase_cost'] - $asset['salvage_value'];
        $monthlyDepreciation = 0;
        
        if ($asset['depreciation_method'] === 'straight_line') {
            // Straight-line: (Cost - Salvage) / Useful Life / 12 months
            $totalMonths = $asset['useful_life_years'] * 12;
            $monthlyDepreciation = $depreciableAmount / $totalMonths;
        } elseif ($asset['depreciation_method'] === 'declining_balance') {
            // Declining balance: Net Book Value × (2 / Useful Life) / 12
            $annualRate = 2 / $asset['useful_life_years'];
            $monthlyDepreciation = $asset['net_book_value'] * $annualRate / 12;
        }
        
        // Ensure we don't depreciate below salvage value
        $maxDepreciation = $asset['net_book_value'] - $asset['salvage_value'];
        $monthlyDepreciation = min($monthlyDepreciation, $maxDepreciation);
        
        return [
            'asset_id' => $assetId,
            'asset_code' => $asset['asset_code'],
            'depreciation_amount' => round($monthlyDepreciation, 2),
            'accumulated_depreciation' => $asset['accumulated_depreciation'],
            'net_book_value' => $asset['net_book_value'],
            'depreciation_method' => $asset['depreciation_method']
        ];
    }

    /**
     * Post monthly depreciation for an asset
     * Req 51.4
     * 
     * @param int $assetId Asset ID
     * @param string $finPeriod Financial period (YYYYMM)
     * @return int|null Journal entry ID or null if no depreciation
     */
    public function postDepreciation(int $assetId, string $finPeriod): ?int {
        $db = Database::getInstance();
        
        $depreciationDate = $finPeriod . '28'; // Last day of month
        $depreciation = $this->calculateDepreciation($assetId, $depreciationDate);
        
        if ($depreciation['depreciation_amount'] <= 0) {
            return null;
        }
        
        // Get asset details
        $asset = $db->query(
            "SELECT * FROM fixed_assets WHERE id = ? AND tenant_id = ?",
            [$assetId, $this->tenantId]
        );
        $asset = $asset[0];
        
        // Create journal entry
        $jeId = $db->insert('journal_entries', [
            'tenant_id' => $this->tenantId,
            'company_code' => $asset['company_code'],
            'fin_period' => $finPeriod,
            'entry_date' => $depreciationDate,
            'description' => "Monthly Depreciation: {$asset['asset_name_en']}",
            'status' => 'posted',
            'created_by' => 'system',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Debit: Depreciation Expense
        $db->insert('journal_entry_lines', [
            'journal_entry_id' => $jeId,
            'account_code' => '5010', // Depreciation Expense
            'debit' => $depreciation['depreciation_amount'],
            'credit' => 0,
            'asset_no' => $asset['asset_code'],
            'description' => "Depreciation Expense"
        ]);
        
        // Credit: Accumulated Depreciation
        $db->insert('journal_entry_lines', [
            'journal_entry_id' => $jeId,
            'account_code' => '1599', // Accumulated Depreciation
            'debit' => 0,
            'credit' => $depreciation['depreciation_amount'],
            'asset_no' => $asset['asset_code'],
            'description' => "Accumulated Depreciation"
        ]);
        
        // Update asset
        $newAccumulated = $asset['accumulated_depreciation'] + $depreciation['depreciation_amount'];
        $newNetBookValue = $asset['purchase_cost'] - $newAccumulated;
        
        $db->execute(
            "UPDATE fixed_assets 
             SET accumulated_depreciation = ?, 
                 net_book_value = ?,
                 updated_at = NOW()
             WHERE id = ?",
            [$newAccumulated, $newNetBookValue, $assetId]
        );
        
        return $jeId;
    }

    /**
     * Dispose of an asset
     * Req 51.5, 51.10
     * 
     * @param int $assetId Asset ID
     * @param array $disposalData Disposal details
     * @return array Disposal result
     */
    public function disposeAsset(int $assetId, array $disposalData): array {
        $db = Database::getInstance();
        
        // Get asset details
        $asset = $db->query(
            "SELECT * FROM fixed_assets WHERE id = ? AND tenant_id = ?",
            [$assetId, $this->tenantId]
        );
        
        if (empty($asset)) {
            throw new \RuntimeException("Asset not found");
        }
        
        $asset = $asset[0];
        
        if ($asset['status'] !== 'active') {
            throw new \RuntimeException("Asset is not active");
        }
        
        $disposalDate = $disposalData['disposal_date'] ?? date('Y-m-d');
        $disposalProceeds = $disposalData['disposal_proceeds'] ?? 0;
        $salvageValue = $disposalData['salvage_value'] ?? 0;
        
        // Calculate gain/loss
        $netBookValue = $asset['net_book_value'];
        $totalProceeds = $disposalProceeds + $salvageValue;
        $gainLoss = $totalProceeds - $netBookValue;
        
        // Post disposal journal entry
        $jeId = $this->postDisposalEntry($asset, $disposalDate, $disposalProceeds, $salvageValue, $gainLoss);
        
        // Update asset status
        $db->execute(
            "UPDATE fixed_assets 
             SET status = 'disposed',
                 disposal_date = ?,
                 disposal_amount = ?,
                 updated_at = NOW()
             WHERE id = ?",
            [$disposalDate, $totalProceeds, $assetId]
        );
        
        return [
            'asset_id' => $assetId,
            'asset_code' => $asset['asset_code'],
            'net_book_value' => $netBookValue,
            'disposal_proceeds' => $disposalProceeds,
            'salvage_value' => $salvageValue,
            'total_proceeds' => $totalProceeds,
            'gain_loss' => $gainLoss,
            'journal_entry_id' => $jeId
        ];
    }

    /**
     * Post asset disposal journal entry
     * 
     * @param array $asset Asset data
     * @param string $disposalDate Disposal date
     * @param float $disposalProceeds Cash proceeds
     * @param float $salvageValue Salvage material value
     * @param float $gainLoss Gain or loss on disposal
     * @return int Journal entry ID
     */
    private function postDisposalEntry(
        array $asset, 
        string $disposalDate, 
        float $disposalProceeds, 
        float $salvageValue, 
        float $gainLoss
    ): int {
        $db = Database::getInstance();
        
        // Create journal entry
        $jeId = $db->insert('journal_entries', [
            'tenant_id' => $this->tenantId,
            'company_code' => $asset['company_code'],
            'entry_date' => $disposalDate,
            'description' => "Asset Disposal: {$asset['asset_name_en']}",
            'status' => 'posted',
            'created_by' => $this->userId ?? 'system',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Debit: Accumulated Depreciation
        $db->insert('journal_entry_lines', [
            'journal_entry_id' => $jeId,
            'account_code' => '1599', // Accumulated Depreciation
            'debit' => $asset['accumulated_depreciation'],
            'credit' => 0,
            'asset_no' => $asset['asset_code'],
            'description' => "Remove Accumulated Depreciation"
        ]);
        
        // Debit: ASSETS CLEARING ACCOUNT (if proceeds)
        if ($disposalProceeds > 0) {
            $db->insert('journal_entry_lines', [
                'journal_entry_id' => $jeId,
                'account_code' => '1590', // ASSETS CLEARING ACCOUNT
                'debit' => $disposalProceeds,
                'credit' => 0,
                'asset_no' => $asset['asset_code'],
                'description' => "Cash from Asset Sale"
            ]);
        }
        
        // Debit: Salvage Material (if salvage)
        if ($salvageValue > 0) {
            $db->insert('journal_entry_lines', [
                'journal_entry_id' => $jeId,
                'account_code' => '1591', // Salvage Material Inventory
                'debit' => $salvageValue,
                'credit' => 0,
                'asset_no' => $asset['asset_code'],
                'description' => "Salvage Material Value"
            ]);
        }
        
        // Debit/Credit: Gain or Loss
        if ($gainLoss != 0) {
            if ($gainLoss < 0) {
                // Loss: Debit Loss account
                $db->insert('journal_entry_lines', [
                    'journal_entry_id' => $jeId,
                    'account_code' => '5020', // Loss on Asset Disposal
                    'debit' => abs($gainLoss),
                    'credit' => 0,
                    'asset_no' => $asset['asset_code'],
                    'description' => "Loss on Disposal"
                ]);
            } else {
                // Gain: Credit Gain account
                $db->insert('journal_entry_lines', [
                    'journal_entry_id' => $jeId,
                    'account_code' => '4020', // Gain on Asset Disposal
                    'debit' => 0,
                    'credit' => $gainLoss,
                    'asset_no' => $asset['asset_code'],
                    'description' => "Gain on Disposal"
                ]);
            }
        }
        
        // Credit: RETIRED ASSETS & EQUIPMENT (original cost)
        $db->insert('journal_entry_lines', [
            'journal_entry_id' => $jeId,
            'account_code' => '1598', // RETIRED ASSETS & EQUIPMENT
            'debit' => 0,
            'credit' => $asset['purchase_cost'],
            'asset_no' => $asset['asset_code'],
            'description' => "Remove Asset Cost"
        ]);
        
        return $jeId;
    }

    /**
     * Revalue an asset
     * Req 51.6
     * 
     * @param int $assetId Asset ID
     * @param float $newValue New revalued amount
     * @param string $revaluationDate Revaluation date
     * @return array Revaluation result
     */
    public function revalueAsset(int $assetId, float $newValue, string $revaluationDate): array {
        $db = Database::getInstance();
        
        // Get asset details
        $asset = $db->query(
            "SELECT * FROM fixed_assets WHERE id = ? AND tenant_id = ?",
            [$assetId, $this->tenantId]
        );
        
        if (empty($asset)) {
            throw new \RuntimeException("Asset not found");
        }
        
        $asset = $asset[0];
        $currentValue = $asset['net_book_value'];
        $revaluationDifference = $newValue - $currentValue;
        
        if (abs($revaluationDifference) < 0.01) {
            return [
                'revaluation_difference' => 0,
                'message' => 'No significant revaluation'
            ];
        }
        
        // Post revaluation journal entry
        $jeId = $this->postRevaluationEntry($asset, $revaluationDifference, $revaluationDate);
        
        // Update asset
        $db->execute(
            "UPDATE fixed_assets 
             SET net_book_value = ?,
                 updated_at = NOW()
             WHERE id = ?",
            [$newValue, $assetId]
        );
        
        return [
            'asset_id' => $assetId,
            'asset_code' => $asset['asset_code'],
            'old_value' => $currentValue,
            'new_value' => $newValue,
            'revaluation_difference' => $revaluationDifference,
            'journal_entry_id' => $jeId
        ];
    }

    /**
     * Post asset revaluation journal entry
     * 
     * @param array $asset Asset data
     * @param float $revaluationDifference Revaluation amount
     * @param string $revaluationDate Revaluation date
     * @return int Journal entry ID
     */
    private function postRevaluationEntry(array $asset, float $revaluationDifference, string $revaluationDate): int {
        $db = Database::getInstance();
        
        // Create journal entry
        $jeId = $db->insert('journal_entries', [
            'tenant_id' => $this->tenantId,
            'company_code' => $asset['company_code'],
            'entry_date' => $revaluationDate,
            'description' => "Asset Revaluation: {$asset['asset_name_en']}",
            'status' => 'posted',
            'created_by' => $this->userId ?? 'system',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($revaluationDifference > 0) {
            // Increase: Debit Asset, Credit Revaluation Reserve
            $db->insert('journal_entry_lines', [
                'journal_entry_id' => $jeId,
                'account_code' => $asset['account_code'],
                'debit' => $revaluationDifference,
                'credit' => 0,
                'asset_no' => $asset['asset_code'],
                'description' => "Asset Revaluation Increase"
            ]);
            
            $db->insert('journal_entry_lines', [
                'journal_entry_id' => $jeId,
                'account_code' => '3300', // Revaluation Reserve (Equity)
                'debit' => 0,
                'credit' => $revaluationDifference,
                'description' => "Revaluation Reserve"
            ]);
        } else {
            // Decrease: Debit Revaluation Reserve, Credit Asset
            $db->insert('journal_entry_lines', [
                'journal_entry_id' => $jeId,
                'account_code' => '3300', // Revaluation Reserve (Equity)
                'debit' => abs($revaluationDifference),
                'credit' => 0,
                'description' => "Revaluation Reserve"
            ]);
            
            $db->insert('journal_entry_lines', [
                'journal_entry_id' => $jeId,
                'account_code' => $asset['account_code'],
                'debit' => 0,
                'credit' => abs($revaluationDifference),
                'asset_no' => $asset['asset_code'],
                'description' => "Asset Revaluation Decrease"
            ]);
        }
        
        return $jeId;
    }

    /**
     * Capitalize or expense asset overhaul/CAPEX
     * Req 51.7
     * 
     * @param int $assetId Asset ID
     * @param float $overhaulCost Overhaul cost
     * @param float $threshold Capitalization threshold
     * @param string $date Transaction date
     * @return array Result
     */
    public function processOverhaul(int $assetId, float $overhaulCost, float $threshold, string $date): array {
        $db = Database::getInstance();
        
        // Get asset
        $asset = $db->query(
            "SELECT * FROM fixed_assets WHERE id = ? AND tenant_id = ?",
            [$assetId, $this->tenantId]
        );
        
        if (empty($asset)) {
            throw new \RuntimeException("Asset not found");
        }
        
        $asset = $asset[0];
        $shouldCapitalize = $overhaulCost >= $threshold;
        
        if ($shouldCapitalize) {
            // Capitalize: Add to asset cost
            $newCost = $asset['purchase_cost'] + $overhaulCost;
            $newNetBookValue = $asset['net_book_value'] + $overhaulCost;
            
            $db->execute(
                "UPDATE fixed_assets 
                 SET purchase_cost = ?,
                     net_book_value = ?,
                     updated_at = NOW()
                 WHERE id = ?",
                [$newCost, $newNetBookValue, $assetId]
            );
            
            $jeId = $this->postCapitalizationEntry($asset, $overhaulCost, $date);
            
            return [
                'decision' => 'capitalize',
                'amount' => $overhaulCost,
                'new_asset_cost' => $newCost,
                'journal_entry_id' => $jeId
            ];
        } else {
            // Expense: Post to expense account
            $jeId = $this->postExpenseEntry($asset, $overhaulCost, $date);
            
            return [
                'decision' => 'expense',
                'amount' => $overhaulCost,
                'journal_entry_id' => $jeId
            ];
        }
    }

    /**
     * Post capitalization journal entry
     */
    private function postCapitalizationEntry(array $asset, float $amount, string $date): int {
        $db = Database::getInstance();
        
        $jeId = $db->insert('journal_entries', [
            'tenant_id' => $this->tenantId,
            'company_code' => $asset['company_code'],
            'entry_date' => $date,
            'description' => "Asset Overhaul Capitalized: {$asset['asset_name_en']}",
            'status' => 'posted',
            'created_by' => $this->userId ?? 'system',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Debit: Asset
        $db->insert('journal_entry_lines', [
            'journal_entry_id' => $jeId,
            'account_code' => $asset['account_code'],
            'debit' => $amount,
            'credit' => 0,
            'asset_no' => $asset['asset_code'],
            'description' => "Capitalized Overhaul"
        ]);
        
        // Credit: Cash/Payable
        $db->insert('journal_entry_lines', [
            'journal_entry_id' => $jeId,
            'account_code' => '2010',
            'debit' => 0,
            'credit' => $amount,
            'description' => "Overhaul Payment"
        ]);
        
        return $jeId;
    }

    /**
     * Post expense journal entry
     */
    private function postExpenseEntry(array $asset, float $amount, string $date): int {
        $db = Database::getInstance();
        
        $jeId = $db->insert('journal_entries', [
            'tenant_id' => $this->tenantId,
            'company_code' => $asset['company_code'],
            'entry_date' => $date,
            'description' => "Asset Maintenance Expense: {$asset['asset_name_en']}",
            'status' => 'posted',
            'created_by' => $this->userId ?? 'system',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Debit: Maintenance Expense
        $db->insert('journal_entry_lines', [
            'journal_entry_id' => $jeId,
            'account_code' => '5030', // Maintenance Expense
            'debit' => $amount,
            'credit' => 0,
            'asset_no' => $asset['asset_code'],
            'description' => "Maintenance Expense"
        ]);
        
        // Credit: Cash/Payable
        $db->insert('journal_entry_lines', [
            'journal_entry_id' => $jeId,
            'account_code' => '2010',
            'debit' => 0,
            'credit' => $amount,
            'description' => "Maintenance Payment"
        ]);
        
        return $jeId;
    }

    /**
     * Generate asset register report
     * Req 51.8
     * 
     * @param string $companyCode Company code
     * @param string|null $category Asset category filter
     * @return array Asset register
     */
    public function generateAssetRegister(string $companyCode, ?string $category = null): array {
        $db = Database::getInstance();
        
        $sql = "SELECT 
                    asset_code,
                    asset_name_en,
                    asset_name_ar,
                    asset_category,
                    account_code,
                    purchase_date,
                    purchase_cost,
                    salvage_value,
                    accumulated_depreciation,
                    net_book_value,
                    depreciation_method,
                    useful_life_years,
                    status
                FROM fixed_assets
                WHERE tenant_id = ?
                AND company_code = ?
                AND deleted_at IS NULL";
        
        $params = [$this->tenantId, $companyCode];
        
        if ($category) {
            $sql .= " AND asset_category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY asset_category, asset_code";
        
        $assets = $db->query($sql, $params);
        
        // Calculate totals
        $totalCost = 0;
        $totalAccumulated = 0;
        $totalNetBookValue = 0;
        
        foreach ($assets as $asset) {
            $totalCost += $asset['purchase_cost'];
            $totalAccumulated += $asset['accumulated_depreciation'];
            $totalNetBookValue += $asset['net_book_value'];
        }
        
        return [
            'company_code' => $companyCode,
            'category' => $category,
            'assets' => $assets,
            'totals' => [
                'total_cost' => $totalCost,
                'total_accumulated_depreciation' => $totalAccumulated,
                'total_net_book_value' => $totalNetBookValue,
                'asset_count' => count($assets)
            ]
        ];
    }

    /**
     * Generate asset movement report (inter-company transfers)
     * Req 51.9
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Movement report
     */
    public function generateAssetMovementReport(string $startDate, string $endDate): array {
        $db = Database::getInstance();
        
        // This would track asset transfers between companies
        // For now, we'll return acquisitions and disposals
        
        $sql = "SELECT 
                    company_code,
                    asset_code,
                    asset_name_en,
                    asset_category,
                    purchase_date,
                    purchase_cost,
                    disposal_date,
                    disposal_amount,
                    status
                FROM fixed_assets
                WHERE tenant_id = ?
                AND (
                    (purchase_date BETWEEN ? AND ?)
                    OR (disposal_date BETWEEN ? AND ?)
                )
                AND deleted_at IS NULL
                ORDER BY company_code, purchase_date, disposal_date";
        
        $movements = $db->query($sql, [
            $this->tenantId,
            $startDate, $endDate,
            $startDate, $endDate
        ]);
        
        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'movements' => $movements
        ];
    }
}
