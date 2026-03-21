<?php
/**
 * Accounting/AutomationController.php
 * 
 * BATCH H & I — Process Automation
 * Endpoints for Payroll and Profit Distribution
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseController;
use Core\Response;
use Modules\Platform\Auth\AuthMiddleware;

class AutomationController extends BaseController
{
    private PayrollService $payrollService;
    private ProfitDistributionService $profitService;

    public function __construct(PayrollService $payrollService, ProfitDistributionService $profitService)
    {
        $this->payrollService = $payrollService;
        $this->profitService = $profitService;
    }

    /**
     * POST /api/v1/accounting/payroll/run
     */
    public function runPayroll($request): Response
    {
        $user = AuthMiddleware::verify($request);
        $companyCode = $request['body']['company_code'] ?? $this->companyCode;
        $finPeriod = $request['body']['fin_period'] ?? date('Ym');

        try {
            $result = $this->payrollService->processPayroll($this->tenantId, $companyCode, $finPeriod, (int)$user->id);
            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/accounting/profit/distribute
     */
    public function distributeProfit($request): Response
    {
        $user = AuthMiddleware::verify($request);
        $companyCode = $request['body']['company_code'] ?? $this->companyCode;
        $finPeriod = $request['body']['fin_period'] ?? date('Ym');

        try {
            $result = $this->profitService->distributeMonthlyProfit($this->tenantId, $companyCode, $finPeriod, (int)$user->id);
            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
