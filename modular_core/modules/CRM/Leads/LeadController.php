<?php
/**
 * CRM/Leads/LeadController.php
 *
 * REST endpoints for lead management.
 *
 * Routes:
 *   GET    /api/v1/crm/leads                 → list()       (authenticated)
 *   POST   /api/v1/crm/leads                 → create()     (authenticated)
 *   POST   /api/v1/crm/leads/capture         → capture()    (public, CSRF-protected)
 *   POST   /api/v1/crm/leads/{id}/convert    → convert()    (authenticated)
 *   POST   /api/v1/crm/leads/import          → import()     (authenticated, triggers Celery)
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
     * Query params: limit (default 50), offset (default 0)
     *
     * @param  array $queryParams  Parsed query string parameters
     * @return Response
     */
    public function list(array $queryParams = []): Response
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
     * @param  array $body       Decoded request body
     * @param  int   $createdBy  Authenticated user ID
     * @return Response
     */
    public function create(array $body, int $createdBy): Response
    {
        try {
            $id   = $this->service->capture($body, $createdBy);
            $lead = $this->service->findById($id);
            return $this->respond($lead, null, 201);
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
     * Public lead capture endpoint — no authentication required.
     *
     * Validates the CSRF token before processing. The session token is read
     * from $_SESSION['csrf_lead_capture'].
     *
     * @param  array $body  Decoded request body (includes csrf_token)
     * @return Response
     */
    public function capture(array $body): Response
    {
        // CSRF validation
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $submittedToken = (string) ($body['csrf_token'] ?? '');
        $sessionToken   = (string) ($_SESSION['csrf_lead_capture'] ?? '');

        if (!$this->formBuilder->validateCsrf($submittedToken, $sessionToken)) {
            return $this->respond(null, 'Invalid or missing CSRF token.', 403);
        }

        // Invalidate token after use (one-time use)
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
    // POST /api/v1/crm/leads/{id}/convert
    // -------------------------------------------------------------------------

    /**
     * Convert a lead into a Contact, Account, and Deal.
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
    // POST /api/v1/crm/leads/import
    // -------------------------------------------------------------------------

    /**
     * Trigger a Celery lead import job.
     *
     * Expects multipart/form-data with:
     *   - file_path     (string) path to uploaded CSV on shared storage
     *   - field_mapping (JSON)   mapping of CSV columns to lead fields
     *
     * Dispatches the 'crm.lead_import' Celery task via RabbitMQ and returns
     * a job reference immediately (async).
     *
     * @param  array $body      Decoded request body
     * @param  int   $userId    Authenticated user ID
     * @return Response
     */
    public function import(array $body, int $userId): Response
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

        // Dispatch Celery task via RabbitMQ (operations > 200ms dispatched async)
        $jobId   = bin2hex(random_bytes(16));
        $payload = [
            'job_id'        => $jobId,
            'file_path'     => $filePath,
            'tenant_id'     => $this->tenantId,
            'company_code'  => $this->companyCode,
            'field_mapping' => $fieldMapping,
            'requested_by'  => $userId,
        ];

        try {
            // Publish to Celery default exchange with task routing key
            // The Celery worker picks this up as 'crm.lead_import'
            $this->service->dispatchImportJob($payload);
        } catch (\Throwable $e) {
            return $this->respond(null, 'Failed to dispatch import job: ' . $e->getMessage(), 500);
        }

        return $this->respond([
            'job_id' => $jobId,
            'status' => 'queued',
            'message' => 'Lead import job queued. Check job status for results.',
        ], null, 202);
    }
}
