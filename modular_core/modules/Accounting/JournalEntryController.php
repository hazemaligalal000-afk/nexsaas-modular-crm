<?php
/**
 * Accounting/JournalEntryController.php
 * 
 * Journal Entry Controller - REST API for journal entries
 * BATCH B — Journal Entry & Voucher Engine
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseController;
use Modules\Platform\Auth\AuthMiddleware;
use Modules\Platform\RBAC\PermissionChecker;

class JournalEntryController extends BaseController
{
    private JournalEntryService $service;

    public function __construct($db, JournalEntryService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/accounting/journal-entries
     * List journal entries with filters
     */
    public function list($request): Response
    {
        $user = AuthMiddleware::verify($request);
        PermissionChecker::require($user, 'accounting.voucher.view');

        $filters = [
            'company_code' => $request->query['company_code'] ?? $this->companyCode,
            'fin_period' => $request->query['fin_period'] ?? null,
            'status' => $request->query['status'] ?? null,
            'voucher_code' => $request->query['voucher_code'] ?? null,
            'date_from' => $request->query['date_from'] ?? null,
            'date_to' => $request->query['date_to'] ?? null,
        ];

        $limit = (int)($request->query['limit'] ?? 50);
        $offset = (int)($request->query['offset'] ?? 0);

        $entries = $this->service->list($filters, $limit, $offset);
        $total = $this->service->count($filters);

        return $this->respond([
            'entries' => $entries,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * GET /api/accounting/journal-entries/{id}
     * Get single journal entry with lines
     */
    public function get($request, $id): Response
    {
        $user = AuthMiddleware::verify($request);
        PermissionChecker::require($user, 'accounting.voucher.view');

        $entry = $this->service->getWithLines((int)$id);

        if ($entry === null) {
            return $this->respond(null, 'Journal entry not found', 404);
        }

        return $this->respond($entry);
    }

    /**
     * POST /api/accounting/journal-entries
     * Create new journal entry
     */
    public function create($request): Response
    {
        $user = AuthMiddleware::verify($request);
        PermissionChecker::require($user, 'accounting.voucher.create');

        $data = json_decode($request->body, true);

        try {
            $entryId = $this->service->create($data, $user->id);
            $entry = $this->service->getWithLines($entryId);

            return $this->respond($entry, null, 201);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/accounting/journal-entries/{id}
     * Update journal entry (draft only)
     */
    public function update($request, $id): Response
    {
        $user = AuthMiddleware::verify($request);
        PermissionChecker::require($user, 'accounting.voucher.edit');

        $data = json_decode($request->body, true);

        try {
            $this->service->update((int)$id, $data, $user->id);
            $entry = $this->service->getWithLines((int)$id);

            return $this->respond($entry);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 400);
        }
    }

    /**
     * POST /api/accounting/journal-entries/{id}/approve
     * Approve journal entry
     */
    public function approve($request, $id): Response
    {
        $user = AuthMiddleware::verify($request);
        PermissionChecker::require($user, 'accounting.voucher.approve');

        try {
            $this->service->approve((int)$id, $user->id);
            $entry = $this->service->getWithLines((int)$id);

            return $this->respond($entry);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 400);
        }
    }

    /**
     * POST /api/accounting/journal-entries/{id}/post
     * Post journal entry
     */
    public function post($request, $id): Response
    {
        $user = AuthMiddleware::verify($request);
        PermissionChecker::require($user, 'accounting.voucher.approve');

        try {
            $this->service->post((int)$id, $user->id);
            $entry = $this->service->getWithLines((int)$id);

            return $this->respond($entry);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 400);
        }
    }

    /**
     * POST /api/accounting/journal-entries/{id}/reverse
     * Reverse journal entry
     */
    public function reverse($request, $id): Response
    {
        $user = AuthMiddleware::verify($request);
        PermissionChecker::require($user, 'accounting.voucher.reverse');

        try {
            $reversalId = $this->service->reverse((int)$id, $user->id);
            $entry = $this->service->getWithLines($reversalId);

            return $this->respond($entry);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/accounting/journal-entries/{id}
     * Soft delete journal entry (draft only)
     */
    public function delete($request, $id): Response
    {
        $user = AuthMiddleware::verify($request);
        PermissionChecker::require($user, 'accounting.voucher.delete');

        try {
            $this->service->delete((int)$id);
            return $this->respond(['message' => 'Journal entry deleted']);
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 400);
        }
    }

    /**
     * GET /api/accounting/journal-entries/next-voucher-number
     * Get next voucher number for company/period
     */
    public function getNextVoucherNumber($request): Response
    {
        $user = AuthMiddleware::verify($request);
        PermissionChecker::require($user, 'accounting.voucher.create');

        $companyCode = $request->query['company_code'] ?? $this->companyCode;
        $finPeriod = $request->query['fin_period'] ?? date('Ym');

        $nextNumber = $this->service->getNextVoucherNumber($companyCode, $finPeriod);

        return $this->respond(['next_voucher_number' => $nextNumber]);
    }

    /**
     * POST /api/accounting/journal-entries/validate-balance
     * Validate double-entry balance
     */
    public function validateBalance($request): Response
    {
        $user = AuthMiddleware::verify($request);

        $data = json_decode($request->body, true);
        $lines = $data['lines'] ?? [];

        $validation = $this->service->validateBalance($lines);

        return $this->respond($validation);
    }
}
