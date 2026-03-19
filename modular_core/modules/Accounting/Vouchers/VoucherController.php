<?php
/**
 * Accounting/Vouchers/VoucherController.php
 *
 * Voucher Controller - REST API endpoints for voucher management
 * Task 30: Journal Entry and Voucher Engine
 *
 * Requirements: 46.1-46.20, 47.10, 47.11
 */

declare(strict_types=1);

namespace Modules\Accounting\Vouchers;

use Core\BaseController;
use Modules\Accounting\Vouchers\VoucherService;
use Modules\Accounting\Vouchers\SettlementVoucherService;
use Modules\Accounting\Reports\TrialBalanceService;

class VoucherController extends BaseController
{
    private VoucherService $voucherService;
    private SettlementVoucherService $settlementService;
    private TrialBalanceService $trialBalanceService;

    public function __construct()
    {
        parent::__construct();
        
        // Initialize services
        $model = new \Core\BaseModel();
        $redis = $this->getRedisConnection();
        
        $this->voucherService = new VoucherService(
            $model,
            $this->getTenantId(),
            $this->getCompanyCode(),
            $redis
        );
        
        $this->settlementService = new SettlementVoucherService(
            $model,
            $this->voucherService,
            $this->getTenantId(),
            $this->getCompanyCode()
        );
        
        $this->trialBalanceService = new TrialBalanceService(
            $model,
            $this->getTenantId()
        );
    }

    /**
     * Create new voucher
     * POST /api/accounting/vouchers
     */
    public function create(): void
    {
        $this->checkPermission('accounting.voucher.create');
        
        $data = $this->getRequestBody();
        $userId = $this->getUserId();
        $isOpeningBalance = $data['is_opening_balance'] ?? false;
        
        $result = $this->voucherService->save($data, $userId, $isOpeningBalance);
        
        $this->respond(
            $result['data'],
            $result['error'],
            $result['success'] ? 201 : 400
        );
    }

    /**
     * Search vouchers
     * GET /api/accounting/vouchers
     */
    public function search(): void
    {
        $this->checkPermission('accounting.voucher.view');
        
        $filters = [
            'company_code' => $_GET['company_code'] ?? null,
            'fin_period' => $_GET['fin_period'] ?? null,
            'voucher_code' => $_GET['voucher_code'] ?? null,
            'section_code' => $_GET['section_code'] ?? null,
            'status' => $_GET['status'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'account_code' => $_GET['account_code'] ?? null,
            'vendor_code' => $_GET['vendor_code'] ?? null,
        ];
        
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $vouchers = $this->voucherService->search($filters, $limit, $offset);
        
        $this->respond($vouchers);
    }

    /**
     * Get voucher by ID
     * GET /api/accounting/vouchers/:id
     */
    public function get(int $id): void
    {
        $this->checkPermission('accounting.voucher.view');
        
        // This would use a method to get voucher with lines
        // For now, return placeholder
        $this->respond(['id' => $id, 'message' => 'Get voucher endpoint']);
    }

    /**
     * Submit voucher for approval
     * POST /api/accounting/vouchers/:id/submit
     */
    public function submit(int $id): void
    {
        $this->checkPermission('accounting.voucher.submit');
        
        $userId = $this->getUserId();
        $result = $this->voucherService->submit($id, $userId);
        
        $this->respond($result['data'], $result['error'], $result['success'] ? 200 : 400);
    }

    /**
     * Approve voucher
     * POST /api/accounting/vouchers/:id/approve
     */
    public function approve(int $id): void
    {
        $this->checkPermission('accounting.voucher.approve');
        
        $userId = $this->getUserId();
        $result = $this->voucherService->approve($id, $userId);
        
        $this->respond($result['data'], $result['error'], $result['success'] ? 200 : 400);
    }

    /**
     * Post voucher to ledger
     * POST /api/accounting/vouchers/:id/post
     */
    public function post(int $id): void
    {
        $this->checkPermission('accounting.voucher.post');
        
        $userId = $this->getUserId();
        $result = $this->voucherService->post($id, $userId);
        
        $this->respond($result['data'], $result['error'], $result['success'] ? 200 : 400);
    }

    /**
     * Reverse voucher
     * POST /api/accounting/vouchers/:id/reverse
     */
    public function reverse(int $id): void
    {
        $this->checkPermission('accounting.voucher.reverse');
        
        $userId = $this->getUserId();
        $result = $this->voucherService->reverse($id, $userId);
        
        $this->respond($result['data'], $result['error'], $result['success'] ? 200 : 400);
    }

    /**
     * Copy voucher
     * POST /api/accounting/vouchers/:id/copy
     */
    public function copy(int $id): void
    {
        $this->checkPermission('accounting.voucher.create');
        
        $userId = $this->getUserId();
        $result = $this->voucherService->copy($id, $userId);
        
        $this->respond($result['data'], $result['error'], $result['success'] ? 201 : 400);
    }

    /**
     * Bulk import vouchers from Excel
     * POST /api/accounting/vouchers/import
     */
    public function import(): void
    {
        $this->checkPermission('accounting.voucher.import');
        
        if (!isset($_FILES['file'])) {
            $this->respond(null, 'No file uploaded', 400);
            return;
        }
        
        $file = $_FILES['file'];
        $userId = $this->getUserId();
        
        $result = $this->voucherService->bulkImport($file['tmp_name'], $userId);
        
        $this->respond($result['data'], $result['error'], $result['success'] ? 200 : 400);
    }

    /**
     * Generate PDF for voucher
     * GET /api/accounting/vouchers/:id/pdf
     */
    public function generatePDF(int $id): void
    {
        $this->checkPermission('accounting.voucher.view');
        
        $result = $this->voucherService->generatePDF($id);
        
        if ($result['success']) {
            // Send file as download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $result['data']['filename'] . '"');
            readfile($result['data']['filepath']);
            unlink($result['data']['filepath']); // Clean up temp file
            exit;
        } else {
            $this->respond(null, $result['error'], 400);
        }
    }

    /**
     * Create settlement voucher
     * POST /api/accounting/vouchers/settlement
     */
    public function createSettlement(): void
    {
        $this->checkPermission('accounting.voucher.create');
        
        $data = $this->getRequestBody();
        $userId = $this->getUserId();
        
        $result = $this->settlementService->createSettlement($data, $userId);
        
        $this->respond($result['data'], $result['error'], $result['success'] ? 201 : 400);
    }

    /**
     * Calculate net settlement
     * GET /api/accounting/vouchers/settlement/calculate
     */
    public function calculateSettlement(): void
    {
        $this->checkPermission('accounting.voucher.view');
        
        $companyCode = $_GET['company_code'] ?? $this->getCompanyCode();
        $currencyCode = $_GET['currency_code'] ?? null;
        $finPeriod = $_GET['fin_period'] ?? date('Ym');
        
        if (!$currencyCode) {
            $this->respond(null, 'currency_code is required', 400);
            return;
        }
        
        $result = $this->settlementService->calculateNetSettlement(
            $companyCode,
            $currencyCode,
            $finPeriod
        );
        
        $this->respond($result['data'], $result['error'], $result['success'] ? 200 : 400);
    }

    /**
     * Get multi-currency trial balance
     * GET /api/accounting/reports/trial-balance
     */
    public function getTrialBalance(): void
    {
        $this->checkPermission('accounting.reports.view');
        
        $companyCode = $_GET['company_code'] ?? $this->getCompanyCode();
        $finPeriod = $_GET['fin_period'] ?? date('Ym');
        $currencyCode = $_GET['currency_code'] ?? null;
        
        $result = $this->trialBalanceService->generateMultiCurrencyTrialBalance(
            $companyCode,
            $finPeriod,
            $currencyCode
        );
        
        $this->respond($result['data'], $result['error'], $result['success'] ? 200 : 400);
    }

    /**
     * Export trial balance to CSV
     * GET /api/accounting/reports/trial-balance/export
     */
    public function exportTrialBalance(): void
    {
        $this->checkPermission('accounting.reports.export');
        
        $companyCode = $_GET['company_code'] ?? $this->getCompanyCode();
        $finPeriod = $_GET['fin_period'] ?? date('Ym');
        $currencyCode = $_GET['currency_code'] ?? null;
        
        $result = $this->trialBalanceService->exportToCSV(
            $companyCode,
            $finPeriod,
            $currencyCode
        );
        
        if ($result['success']) {
            // Send file as download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $result['data']['filename'] . '"');
            readfile($result['data']['filepath']);
            unlink($result['data']['filepath']); // Clean up temp file
            exit;
        } else {
            $this->respond(null, $result['error'], 400);
        }
    }

    /**
     * Helper: Get Redis connection
     */
    private function getRedisConnection()
    {
        try {
            $redis = new \Redis();
            $redis->connect($_ENV['REDIS_HOST'] ?? '127.0.0.1', (int)($_ENV['REDIS_PORT'] ?? 6379));
            return $redis;
        } catch (\Exception $e) {
            return null; // Redis is optional
        }
    }

    /**
     * Helper: Get tenant ID from session/JWT
     */
    private function getTenantId(): string
    {
        return $_SESSION['tenant_id'] ?? 'default-tenant';
    }

    /**
     * Helper: Get company code from session/request
     */
    private function getCompanyCode(): string
    {
        return $_SESSION['company_code'] ?? '01';
    }

    /**
     * Helper: Get user ID from session/JWT
     */
    private function getUserId(): int
    {
        return (int)($_SESSION['user_id'] ?? 1);
    }

    /**
     * Helper: Get request body as array
     */
    private function getRequestBody(): array
    {
        $body = file_get_contents('php://input');
        return json_decode($body, true) ?? [];
    }

    /**
     * Helper: Check permission
     */
    private function checkPermission(string $permission): void
    {
        // Placeholder - would integrate with RBAC system
        // For now, assume all permissions granted
    }
}
