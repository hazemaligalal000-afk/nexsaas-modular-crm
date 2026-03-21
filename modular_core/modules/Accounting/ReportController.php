<?php
/**
 * Accounting/ReportController.php
 * 
 * BATCH J — Financial Statements
 * Reports: Trial Balance, P&L, Balance Sheet
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseController;
use Core\Response;
use Modules\Platform\Auth\AuthMiddleware;

class ReportController extends BaseController
{
    private StatementService $service;

    public function __construct(StatementService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/v1/accounting/reports/trial-balance
     * Get Trial Balance per period
     */
    public function trialBalance($request): Response
    {
        $user = AuthMiddleware::verify($request);
        $companyCode = $request['queries']['company_code'] ?? $this->companyCode;
        $tenantId = $this->tenantId;
        $finPeriod = $request['queries']['fin_period'] ?? date('Ym');

        try {
            $data = $this->service->getTrialBalance($tenantId, $companyCode, $finPeriod);
            return $this->respond($data);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/accounting/reports/profit-loss
     * Get Profit & Loss per period range
     */
    public function profitLoss($request): Response
    {
        $user = AuthMiddleware::verify($request);
        $companyCode = $request['queries']['company_code'] ?? $this->companyCode;
        $tenantId = $this->tenantId;
        $start = $request['queries']['start_period'] ?? date('Y01');
        $end = $request['queries']['end_period'] ?? date('Ym');

        try {
            $data = $this->service->getProfitLoss($tenantId, $companyCode, $start, $end);
            return $this->respond($data);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/accounting/reports/balance-sheet
     * Get Balance Sheet per period
     */
    public function balanceSheet($request): Response
    {
        $user = AuthMiddleware::verify($request);
        $companyCode = $request['queries']['company_code'] ?? $this->companyCode;
        $tenantId = $this->tenantId;
        $finPeriod = $request['queries']['fin_period'] ?? date('Ym');

        try {
            $data = $this->service->getBalanceSheet($tenantId, $companyCode, $finPeriod);
            return $this->respond($data);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
