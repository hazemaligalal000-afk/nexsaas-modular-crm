<?php
/**
 * CRM/Leads/LeadController.php
 *
 * REST endpoints for lead management.
 *
 * Routes:
 *   GET    /api/v1/crm/leads                 → index()    (authenticated)
 *   POST   /api/v1/crm/leads                 → create()   (authenticated)
 *   GET    /api/v1/crm/leads/{id}            → show()     (authenticated)
 *   PUT    /api/v1/crm/leads/{id}            → update()   (authenticated)
 *   DELETE /api/v1/crm/leads/{id}            → delete()   (authenticated)
 *   POST   /api/v1/crm/leads/{id}/convert    → convert()  (authenticated)
 *   POST   /api/v1/crm/leads/capture         → capture()  (public, CSRF-protected)
 *   POST   /api/v1/crm/leads/import          → import()   (authenticated, async Celery)
 *
 * Requirements: 7.1, 7.5
 */

declare(strict_types=1);

namespace CRM\Leads;

use Core\BaseController;
use Core\Response;

class LeadController extends BaseController
{
    private LeadService     $service;
    private LeadFormBuilder $formBuilder;

    public function __construct(LeadService $service, LeadFormBuilder $formBuilder)
    {
        $this->service     = $service;
        $this->formBuilder = $formBuilder;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/leads
    // -------------------------------------------------------------------------

    /**
     * List leads for the current tenant.
     *
     * Query params: limit (default 50), offset (default 0), status, source
     *
     * @param  array $queryParams
     * @return Response
     */
    public function index(array $queryParams = []): Response
    {
        try {
            $limit  = max(1, min(200, (int) ($queryParams['limit']  ?? 50)));
            $offset = max(0, (int) ($queryParams['offset'] ?? 0));

            $leads = $this->service->list($limit, $offset);
            return $this->respond($leads);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/leads
    // -------------------------------------------------------------------------

    /**
     * Create a new lead (authenticated).
     *
     * @param  array $body
     * @return Response
     */
    public function create(array $body): Response
    {
        try {
            $id   = $this->service->capture($body, (int) $this->userId);
            $lead = $this->service->findById($id);
            return $this->respond($lead, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            $code = $e->getCode() === 409 ? 409 : 500;
            return $this->respond(null, $e->getMessage(), $code);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/leads/{id}
    // -------------------------------------------------------------------------

    /**
     * Show a single lead.
     *
     * @param  int $id
     * @return Response
     */
    public function show(int $id): Response
    {
        try {
            $lead = $this->service->findById($id);

            if ($lead === null) {
                return $this->respond(null, 'Lead not found.', 404);
            }

            return $this->respond($lead);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/crm/leads/{id}
    // -------------------------------------------------------------------------

    /**
     * Update a lead.
     *
     * @param  int   $id
     * @param  array $body
     * @return Response
     */
    // -------------------------------------------------------------------------
    // PUT /api/v1/crm/leads/{id}
    // -------------------------------------------------------------------------

    /**
     * Update a lead record (authenticated).
     *
     * @param  int   $id    Lead ID
     * @param  array $body  Decoded request body
     * @return Response
     */
    public function update(int $id, array $body): Response
    {
        try {
            $updated = $this->service->update($id, $body);
            if (!$updated) {
                return $this->respond(null, 'Lead not found or no changes applied.', 404);
            }
            $lead = $this->service->findById($id);
            return $this->respond($lead);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/leads/{id}/convert
    // -------------------------------------------------------------------------
            $updated = $this->service->update($id, $body);

            if (!$updated) {
                return $this->respond(null, 'Lead not found or no changes made.', 404);
            }

            $lead = $this->service->findById($id);
            return $this->respond($lead);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/crm/leads/{id}
    // -------------------------------------------------------------------------

    /**
     * Soft-delete a lead.
     *
     * @param  int $id
     * @return Response
     */
    public function delete(int $id): Response
    {
        try {
            $deleted = $this->service->delete($id);

            if (!$deleted) {
                return $this->respond(null, 'Lead not found.', 404);
            }

            return $this->respond(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/leads/{id}/convert
    // -------------------------------------------------------------------------

    /**
     * Convert a lead into a Contact, Account, and Deal (Requirement 7.5).
     *
     * @param  int $id  Lead ID
     * @return Response
     */
    public function convert(int $id): Response
    {
        try {
            $result = $this->service->convert($id);
            return $this->respond($result);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/leads/capture  (public, no auth, CSRF-protected)
    // -------------------------------------------------------------------------

    /**
     * Public lead capture endpoint — no authentication required (Requirement 7.2).
     *
     * Validates the CSRF token before processing.
     *
     * @param  array $body  Decoded request body (includes csrf_token)
     * @return Response
     */
    public function capture(array $body): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $submittedToken = (string) ($body['csrf_token'] ?? '');
        $sessionToken   = (string) ($_SESSION['csrf_lead_capture'] ?? '');

        if (!$this->formBuilder->validateCsrf($submittedToken, $sessionToken)) {
            return $this->respond(null, 'Invalid or missing CSRF token.', 403);
        }

        unset($_SESSION['csrf_lead_capture']);

        try {
            $id   = $this->service->capture($body, 0);
            $lead = $this->service->findById($id);
            return $this->respond($lead, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/leads/import
    // -------------------------------------------------------------------------

    /**
     * Trigger an async Celery lead import job (Requirement 7.4).
     *
     * Expects multipart/form-data or JSON with:
     *   - file_path     (string) path to uploaded CSV on shared storage
     *   - field_mapping (JSON)   mapping of CSV columns to lead fields
     *
     * Returns 202 Accepted with a job_id for status polling.
     *
     * @param  array $body
     * @return Response
     */
    public function import(array $body): Response
    {
        $filePath = isset($body['file_path']) ? trim((string) $body['file_path']) : '';

        if ($filePath === '') {
            return $this->respond(null, 'file_path is required.', 422);
        }

        $fieldMapping = [];
        if (isset($body['field_mapping'])) {
            $fieldMapping = is_array($body['field_mapping'])
                ? $body['field_mapping']
                : json_decode((string) $body['field_mapping'], true) ?? [];
        }

        $jobId   = bin2hex(random_bytes(16));
        $payload = [
            'job_id'        => $jobId,
            'file_path'     => $filePath,
            'tenant_id'     => $this->tenantId,
            'company_code'  => $this->companyCode,
            'field_mapping' => $fieldMapping,
            'requested_by'  => (int) $this->userId,
        ];

        try {
            $this->service->dispatchImportJob($payload);
        } catch (\Throwable $e) {
            return $this->respond(null, 'Failed to dispatch import job: ' . $e->getMessage(), 500);
        }

        return $this->respond([
            'job_id'   => $jobId,
            'status'   => 'queued',
            'message'  => 'Lead import job queued. Check job status for results.',
        ], null, 202);
    }
}
