<?php
/**
 * Accounting/ExportController.php
 * 
 * BATCH K — Exporting & Portability
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseController;
use Core\Response;
use Modules\Platform\Auth\AuthMiddleware;

class ExportController extends BaseController
{
    private COAService $coaService;

    public function __construct(COAService $coaService)
    {
        $this->coaService = $coaService;
    }

    /**
     * GET /api/v1/accounting/coa/export
     */
    public function exportCOA($request): Response
    {
        AuthMiddleware::verify($request);
        $companyCode = $request['queries']['company_code'] ?? $this->companyCode;

        try {
            $data = $this->coaService->exportToExcel($this->tenantId, $companyCode);
            // In a real app, this would stream a file. For now, we return the data structure.
            return $this->respond($data, 'Excel data prepared successfully');
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
