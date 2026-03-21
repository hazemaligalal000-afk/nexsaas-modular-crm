<?php
/**
 * Accounting/JournalVoucherController.php
 * 
 * BATCH B — Journal Entry & Voucher Engine
 * Handles full life-cycle of a journal voucher.
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseController;
use Core\Response;
use Modules\Platform\Auth\AuthMiddleware;

class JournalVoucherController extends BaseController
{
    private JournalEntryService $service;

    public function __construct(JournalEntryService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/v1/accounting/vouchers
     * List vouchers with filters
     */
    public function index($request): Response
    {
        $user = AuthMiddleware::verify($request);
        $filters = $request['queries'] ?? [];
        $limit = (int)($filters['limit'] ?? 50);
        $offset = (int)($filters['offset'] ?? 0);

        try {
            $vouchers = $this->service->list($filters, $limit, $offset);
            return $this->respond($vouchers);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/accounting/vouchers/{id}
     * Get single voucher with lines
     */
    public function show($request, int $id): Response
    {
        $user = AuthMiddleware::verify($request);
        try {
            $voucher = $this->service->getWithLines($id);
            if (!$voucher) {
                return $this->respond(null, 'Voucher not found', 404);
            }
            return $this->respond($voucher);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/accounting/vouchers
     * Create new voucher (Draft)
     */
    public function store($request): Response
    {
        $user = AuthMiddleware::verify($request);
        $data = $request['body'] ?? [];

        try {
            $id = $this->service->create($data, (int)$user->id);
            return $this->respond(['id' => $id], 'Voucher created successfully', 201);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/accounting/vouchers/{id}/approve
     * Approve a submitted voucher
     */
    public function approve($request, int $id): Response
    {
        $user = AuthMiddleware::verify($request);
        try {
            $this->service->approve($id, (int)$user->id);
            return $this->respond(['id' => $id], 'Voucher approved');
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/accounting/vouchers/{id}/post
     * Post an approved voucher to ledger
     */
    public function post($request, int $id): Response
    {
        $user = AuthMiddleware::verify($request);
        try {
            $this->service->post($id, (int)$user->id);
            return $this->respond(['id' => $id], 'Voucher posted to ledger');
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/accounting/vouchers/{id}/reverse
     * Create a reversal for a posted voucher (Rule 6.5)
     */
    public function reverse($request, int $id): Response
    {
        $user = AuthMiddleware::verify($request);
        try {
            $newId = $this->service->reverse($id, (int)$user->id);
            return $this->respond(['id' => $newId], 'Voucher reversed successfully');
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 400);
        }
    }

    /**
     * GET /api/v1/accounting/vouchers/next-number
     * Get next available voucher number for a period
     */
    public function getNextNumber($request): Response
    {
        $companyCode = $request['queries']['company_code'] ?? $this->companyCode;
        $finPeriod = $request['queries']['fin_period'] ?? date('Ym');

        try {
            $no = $this->service->getNextVoucherNumber($companyCode, $finPeriod);
            return $this->respond(['next_voucher_no' => $no]);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
