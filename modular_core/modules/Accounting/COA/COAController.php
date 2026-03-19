<?php

namespace Accounting\COA;

use Core\BaseController;

class COAController extends BaseController
{
    private COAService $coaService;
    private AllocationService $allocationService;
    
    public function __construct()
    {
        parent::__construct();
        $this->coaService = new COAService();
        $this->allocationService = new AllocationService();
    }
    
    /**
     * GET /api/v1/accounting/coa
     * Get all accounts for a company
     */
    public function index(): void
    {
        $this->requirePermission('accounting.coa.view');
        
        $tenantId = $this->getTenantId();
        $companyCode = $this->getRequiredParam('company_code');
        $level = $this->getOptionalParam('level', null);
        
        try {
            $model = new COAModel();
            $accounts = $model->getAllAccounts($tenantId, $companyCode, $level);
            
            $this->respond($accounts);
        } catch (\Exception $e) {
            $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/v1/accounting/coa/{accountCode}
     * Get account details
     */
    public function show(string $accountCode): void
    {
        $this->requirePermission('accounting.coa.view');
        
        $tenantId = $this->getTenantId();
        $companyCode = $this->getRequiredParam('company_code');
        
        try {
            $model = new COAModel();
            $account = $model->getByCode($tenantId, $companyCode, $accountCode);
            
            if (!$account) {
                $this->respond(null, 'Account not found', 404);
                return;
            }
            
            $this->respond($account);
        } catch (\Exception $e) {
            $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/v1/accounting/coa/{accountCode}/balance
     * Get account balance viewer
     */
    public function balance(string $accountCode): void
    {
        $this->requirePermission('accounting.coa.view');
        
        $tenantId = $this->getTenantId();
        $companyCode = $this->getRequiredParam('company_code');
        $finPeriod = $this->getRequiredParam('fin_period');
        
        try {
            $balanceData = $this->coaService->getAccountBalanceViewer($tenantId, $companyCode, $accountCode, $finPeriod);
            $this->respond($balanceData);
        } catch (\Exception $e) {
            $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/v1/accounting/coa/{accountCode}/usage
     * Get account usage report
     */
    public function usage(string $accountCode): void
    {
        $this->requirePermission('accounting.coa.view');
        
        $tenantId = $this->getTenantId();
        $companyCode = $this->getRequiredParam('company_code');
        $startDate = $this->getRequiredParam('start_date');
        $endDate = $this->getRequiredParam('end_date');
        
        try {
            $usageReport = $this->coaService->getAccountUsageReport($tenantId, $companyCode, $accountCode, $startDate, $endDate);
            $this->respond($usageReport);
        } catch (\Exception $e) {
            $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/v1/accounting/coa/merge
     * Merge accounts
     */
    public function merge(): void
    {
        $this->requirePermission('accounting.coa.merge');
        
        $tenantId = $this->getTenantId();
        $companyCode = $this->getRequiredParam('company_code');
        $sourceCode = $this->getRequiredParam('source_account_code');
        $targetCode = $this->getRequiredParam('target_account_code');
        $userId = $this->getUserId();
        
        try {
            $result = $this->coaService->mergeAccounts($tenantId, $companyCode, $sourceCode, $targetCode, $userId);
            $this->respond($result);
        } catch (\Exception $e) {
            $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/v1/accounting/coa/opening-balance
     * Create opening balance
     */
    public function createOpeningBalance(): void
    {
        $this->requirePermission('accounting.coa.opening_balance');
        
        $tenantId = $this->getTenantId();
        $companyCode = $this->getRequiredParam('company_code');
        $accountCode = $this->getRequiredParam('account_code');
        $finPeriod = $this->getRequiredParam('fin_period');
        $debitAmount = (float)$this->getRequiredParam('debit_amount');
        $creditAmount = (float)$this->getRequiredParam('credit_amount');
        $currencyCode = $this->getRequiredParam('currency_code');
        $exchangeRate = (float)$this->getOptionalParam('exchange_rate', 1.0);
        $userId = $this->getUserId();
        
        try {
            $result = $this->coaService->createOpeningBalance(
                $tenantId, $companyCode, $accountCode, $finPeriod,
                $debitAmount, $creditAmount, $currencyCode, $exchangeRate, $userId
            );
            $this->respond($result);
        } catch (\Exception $e) {
            $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/v1/accounting/coa/export
     * Export COA to Excel
     */
    public function export(): void
    {
        $this->requirePermission('accounting.coa.export');
        
        $tenantId = $this->getTenantId();
        $companyCode = $this->getRequiredParam('company_code');
        
        try {
            $filepath = $this->coaService->exportToExcel($tenantId, $companyCode);
            
            // Send file download
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            
            // Clean up temp file
            unlink($filepath);
            exit;
        } catch (\Exception $e) {
            $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/v1/accounting/coa/import
     * Import COA from Excel
     */
    public function import(): void
    {
        $this->requirePermission('accounting.coa.import');
        
        $tenantId = $this->getTenantId();
        $companyCode = $this->getRequiredParam('company_code');
        $userId = $this->getUserId();
        
        if (!isset($_FILES['file'])) {
            $this->respond(null, 'No file uploaded', 400);
            return;
        }
        
        $file = $_FILES['file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->respond(null, 'File upload error', 400);
            return;
        }
        
        try {
            $result = $this->coaService->importFromExcel($tenantId, $companyCode, $file['tmp_name'], $userId);
            $this->respond($result);
        } catch (\Exception $e) {
            $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/v1/accounting/coa/stale-wip
     * Get stale WIP accounts
     */
    public function staleWIP(): void
    {
        $this->requirePermission('accounting.coa.view');
        
        $tenantId = $this->getTenantId();
        $companyCode = $this->getRequiredParam('company_code');
        $days = (int)$this->getOptionalParam('days', 90);
        
        try {
            $staleAccounts = $this->coaService->getStaleWIPAccounts($tenantId, $companyCode, $days);
            $this->respond([
                'stale_accounts' => $staleAccounts,
                'threshold_days' => $days,
                'count' => count($staleAccounts)
            ]);
        } catch (\Exception $e) {
            $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/v1/accounting/allocation-rules
     * Get allocation rules for an account
     */
    public function getAllocationRules(): void
    {
        $this->requirePermission('accounting.allocation.view');
        
        $tenantId = $this->getTenantId();
        $companyCode = $this->getRequiredParam('company_code');
        $sourceAccountCode = $this->getRequiredParam('source_account_code');
        
        try {
            $rules = $this->allocationService->getAllocationRules($tenantId, $companyCode, $sourceAccountCode);
            $this->respond($rules);
        } catch (\Exception $e) {
            $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/v1/accounting/allocation-rules
     * Create or update allocation rule
     */
    public function saveAllocationRule(): void
    {
        $this->requirePermission('accounting.allocation.manage');
        
        $tenantId = $this->getTenantId();
        $companyCode = $this->getRequiredParam('company_code');
        $sourceAccountCode = $this->getRequiredParam('source_account_code');
        $targetAccountCode = $this->getRequiredParam('target_account_code');
        $percentage = (float)$this->getRequiredParam('percentage');
        $userId = $this->getUserId();
        
        try {
            $result = $this->allocationService->saveAllocationRule(
                $tenantId, $companyCode, $sourceAccountCode, $targetAccountCode, $percentage, $userId
            );
            $this->respond($result);
        } catch (\Exception $e) {
            $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /api/v1/accounting/allocation-rules/{ruleId}
     * Delete allocation rule
     */
    public function deleteAllocationRule(int $ruleId): void
    {
        $this->requirePermission('accounting.allocation.manage');
        
        $tenantId = $this->getTenantId();
        $companyCode = $this->getRequiredParam('company_code');
        
        try {
            $result = $this->allocationService->deleteAllocationRule($tenantId, $companyCode, $ruleId);
            $this->respond(['deleted' => $result]);
        } catch (\Exception $e) {
            $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/v1/accounting/allocation-rules/distribute
     * Manually trigger distribution for a journal line
     */
    public function distributeAllocation(): void
    {
        $this->requirePermission('accounting.allocation.distribute');
        
        $tenantId = $this->getTenantId();
        $companyCode = $this->getRequiredParam('company_code');
        $journalLineId = (int)$this->getRequiredParam('journal_line_id');
        $userId = $this->getUserId();
        
        try {
            $result = $this->allocationService->distribute($journalLineId, $tenantId, $companyCode, $userId);
            $this->respond($result);
        } catch (\Exception $e) {
            $this->respond(null, $e->getMessage(), 500);
        }
    }
}
