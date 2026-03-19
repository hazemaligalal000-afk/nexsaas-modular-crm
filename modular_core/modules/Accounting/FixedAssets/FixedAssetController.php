<?php
namespace Modules\Accounting\FixedAssets;

use Core\BaseController;

/**
 * FixedAssetController: Fixed Asset Management API
 * Task 35.2 - 35.6
 * Requirements: 51.1 - 51.10
 */
class FixedAssetController extends BaseController {
    
    private FixedAssetService $assetService;
    
    public function __construct() {
        parent::__construct();
        $this->assetService = new FixedAssetService();
    }

    /**
     * POST /api/v1/accounting/fixed-assets
     * Acquire a new fixed asset
     */
    public function acquireAsset(): void {
        $data = $this->getRequestBody();
        
        try {
            $assetId = $this->assetService->acquireAsset($data);
            
            $this->respond([
                'message' => 'Asset acquired successfully',
                'asset_id' => $assetId
            ], 201);
        } catch (\InvalidArgumentException $e) {
            $this->respond(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            $this->respond(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/accounting/fixed-assets/{id}/depreciation
     * Calculate depreciation for an asset
     */
    public function calculateDepreciation(int $id): void {
        $date = $_GET['date'] ?? date('Y-m-d');
        
        try {
            $depreciation = $this->assetService->calculateDepreciation($id, $date);
            $this->respond($depreciation);
        } catch (\Exception $e) {
            $this->respond(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/accounting/fixed-assets/{id}/depreciation
     * Post monthly depreciation
     */
    public function postDepreciation(int $id): void {
        $data = $this->getRequestBody();
        $finPeriod = $data['fin_period'] ?? date('Ym');
        
        try {
            $jeId = $this->assetService->postDepreciation($id, $finPeriod);
            
            if ($jeId) {
                $this->respond([
                    'message' => 'Depreciation posted successfully',
                    'journal_entry_id' => $jeId
                ]);
            } else {
                $this->respond([
                    'message' => 'No depreciation posted (asset fully depreciated or inactive)'
                ]);
            }
        } catch (\Exception $e) {
            $this->respond(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/accounting/fixed-assets/{id}/dispose
     * Dispose of an asset
     */
    public function disposeAsset(int $id): void {
        $data = $this->getRequestBody();
        
        try {
            $result = $this->assetService->disposeAsset($id, $data);
            
            $this->respond([
                'message' => 'Asset disposed successfully',
                'disposal_result' => $result
            ]);
        } catch (\Exception $e) {
            $this->respond(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/accounting/fixed-assets/{id}/revalue
     * Revalue an asset
     */
    public function revalueAsset(int $id): void {
        $data = $this->getRequestBody();
        
        $newValue = $data['new_value'] ?? null;
        $revaluationDate = $data['revaluation_date'] ?? date('Y-m-d');
        
        if (!$newValue) {
            $this->respond(['error' => 'new_value is required'], 400);
            return;
        }
        
        try {
            $result = $this->assetService->revalueAsset($id, (float)$newValue, $revaluationDate);
            
            $this->respond([
                'message' => 'Asset revalued successfully',
                'revaluation_result' => $result
            ]);
        } catch (\Exception $e) {
            $this->respond(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/accounting/fixed-assets/{id}/overhaul
     * Process asset overhaul/CAPEX
     */
    public function processOverhaul(int $id): void {
        $data = $this->getRequestBody();
        
        $overhaulCost = $data['overhaul_cost'] ?? null;
        $threshold = $data['capitalization_threshold'] ?? 5000; // Default threshold
        $date = $data['date'] ?? date('Y-m-d');
        
        if (!$overhaulCost) {
            $this->respond(['error' => 'overhaul_cost is required'], 400);
            return;
        }
        
        try {
            $result = $this->assetService->processOverhaul($id, (float)$overhaulCost, (float)$threshold, $date);
            
            $this->respond([
                'message' => 'Overhaul processed successfully',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            $this->respond(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/accounting/fixed-assets/register
     * Generate asset register report
     */
    public function getAssetRegister(): void {
        $companyCode = $_GET['company_code'] ?? null;
        $category = $_GET['category'] ?? null;
        
        if (!$companyCode) {
            $this->respond(['error' => 'company_code is required'], 400);
            return;
        }
        
        try {
            $register = $this->assetService->generateAssetRegister($companyCode, $category);
            $this->respond($register);
        } catch (\Exception $e) {
            $this->respond(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/accounting/fixed-assets/movements
     * Generate asset movement report
     */
    public function getAssetMovements(): void {
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        try {
            $movements = $this->assetService->generateAssetMovementReport($startDate, $endDate);
            $this->respond($movements);
        } catch (\Exception $e) {
            $this->respond(['error' => $e->getMessage()], 500);
        }
    }
}
