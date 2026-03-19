<?php
namespace Modules\Accounting\FixedAssets;

use Core\BaseModel;

/**
 * FixedAssetModel: Fixed Asset Data Model
 * Task 35.1
 * Requirement: 51.1
 */
class FixedAssetModel extends BaseModel {
    
    protected string $table = 'fixed_assets';

    /**
     * Asset categories as per Requirement 51.1
     */
    const CATEGORIES = [
        'BUILDINGS' => 'Buildings',
        'FENCES' => 'Fences',
        'PORTA_CABINS' => 'Porta Cabins',
        'PLANT_EQUIPMENT' => 'Plant Equipment',
        'MARINE_EQUIPMENT' => 'Marine Equipment',
        'FURNITURE' => 'Furniture',
        'COMPUTER_HARDWARE' => 'Computer Hardware',
        'SOFTWARE' => 'Software',
        'VEHICLES' => 'Vehicles',
        'CRANES' => 'Cranes',
        'OTHER' => 'Other'
    ];

    /**
     * Depreciation methods
     */
    const DEPRECIATION_METHODS = [
        'straight_line' => 'Straight Line',
        'declining_balance' => 'Declining Balance'
    ];

    /**
     * Asset statuses
     */
    const STATUSES = [
        'active' => 'Active',
        'disposed' => 'Disposed',
        'retired' => 'Retired'
    ];

    /**
     * Get all assets for a company
     * 
     * @param string $companyCode Company code
     * @param string|null $status Status filter
     * @return array
     */
    public function getAssetsByCompany(string $companyCode, ?string $status = null): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE company_code = ?";
        
        $params = [$companyCode];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY asset_code";
        
        return $this->scopeQuery($sql, $params);
    }

    /**
     * Get assets by category
     * 
     * @param string $companyCode Company code
     * @param string $category Asset category
     * @return array
     */
    public function getAssetsByCategory(string $companyCode, string $category): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE company_code = ? 
                AND asset_category = ?
                ORDER BY asset_code";
        
        return $this->scopeQuery($sql, [$companyCode, $category]);
    }

    /**
     * Get asset by code
     * 
     * @param string $companyCode Company code
     * @param string $assetCode Asset code
     * @return array|null
     */
    public function getAssetByCode(string $companyCode, string $assetCode): ?array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE company_code = ? 
                AND asset_code = ?";
        
        $rows = $this->scopeQuery($sql, [$companyCode, $assetCode]);
        return $rows[0] ?? null;
    }

    /**
     * Get active assets requiring depreciation
     * 
     * @param string $companyCode Company code
     * @return array
     */
    public function getAssetsForDepreciation(string $companyCode): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE company_code = ? 
                AND status = 'active'
                AND net_book_value > salvage_value
                ORDER BY asset_code";
        
        return $this->scopeQuery($sql, [$companyCode]);
    }

    /**
     * Get assets by account code
     * 
     * @param string $companyCode Company code
     * @param string $accountCode COA account code
     * @return array
     */
    public function getAssetsByAccount(string $companyCode, string $accountCode): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE company_code = ? 
                AND account_code = ?
                ORDER BY asset_code";
        
        return $this->scopeQuery($sql, [$companyCode, $accountCode]);
    }

    /**
     * Get asset summary by category
     * 
     * @param string $companyCode Company code
     * @return array
     */
    public function getAssetSummaryByCategory(string $companyCode): array {
        $sql = "SELECT 
                    asset_category,
                    COUNT(*) as asset_count,
                    SUM(purchase_cost) as total_cost,
                    SUM(accumulated_depreciation) as total_depreciation,
                    SUM(net_book_value) as total_net_book_value
                FROM {$this->table}
                WHERE company_code = ?
                AND status = 'active'
                GROUP BY asset_category
                ORDER BY asset_category";
        
        return $this->scopeQuery($sql, [$companyCode]);
    }

    /**
     * Get fully depreciated assets
     * 
     * @param string $companyCode Company code
     * @return array
     */
    public function getFullyDepreciatedAssets(string $companyCode): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE company_code = ? 
                AND status = 'active'
                AND net_book_value <= salvage_value
                ORDER BY asset_code";
        
        return $this->scopeQuery($sql, [$companyCode]);
    }

    /**
     * Search assets
     * 
     * @param string $companyCode Company code
     * @param string $searchTerm Search term
     * @return array
     */
    public function searchAssets(string $companyCode, string $searchTerm): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE company_code = ?
                AND (
                    asset_code ILIKE ?
                    OR asset_name_en ILIKE ?
                    OR asset_name_ar ILIKE ?
                )
                ORDER BY asset_code
                LIMIT 50";
        
        $searchPattern = "%{$searchTerm}%";
        return $this->scopeQuery($sql, [$companyCode, $searchPattern, $searchPattern, $searchPattern]);
    }

    /**
     * Validate asset category
     * 
     * @param string $category Category to validate
     * @return bool
     */
    public static function isValidCategory(string $category): bool {
        return array_key_exists($category, self::CATEGORIES);
    }

    /**
     * Validate depreciation method
     * 
     * @param string $method Method to validate
     * @return bool
     */
    public static function isValidDepreciationMethod(string $method): bool {
        return array_key_exists($method, self::DEPRECIATION_METHODS);
    }

    /**
     * Validate asset status
     * 
     * @param string $status Status to validate
     * @return bool
     */
    public static function isValidStatus(string $status): bool {
        return array_key_exists($status, self::STATUSES);
    }
}
