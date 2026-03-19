<?php
/**
 * CRM/Workflows/WorkflowController.php
 *
 * REST controller for Workflow CRUD + enable/disable/clone.
 *
 * Routes (registered in module.json):
 *   GET    /api/v1/crm/workflows
 *   POST   /api/v1/crm/workflows
 *   GET    /api/v1/crm/workflows/{id}
 *   PUT    /api/v1/crm/workflows/{id}
 *   DELETE /api/v1/crm/workflows/{id}
 *   POST   /api/v1/crm/workflows/{id}/enable
 *   POST   /api/v1/crm/workflows/{id}/disable
 *   POST   /api/v1/crm/workflows/{id}/clone
 *
 * Requirements: 14.8
 */

declare(strict_types=1);

namespace CRM\Workflows;

use Core\BaseController;
use Core\Response;

class WorkflowController extends BaseController
{
    private WorkflowService $service;

    public function __construct(WorkflowService $service)
    {
        $this->service = $service;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/workflows
    // -------------------------------------------------------------------------

    public function index(array $queryParams = []): Response
    {
        $limit  = max(1, min(200, (int) ($queryParams['limit']  ?? 50)));
        $offset = max(0, (int) ($queryParams['offset'] ?? 0));

        $workflows = $this->service->list($limit, $offset);
        return $this->respond($workflows);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/workflows/{id}
    // -------------------------------------------------------------------------

    public function show(int $id): Response
    {
        $workflow = $this->service->find($id);
        if ($workflow === null) {
            return $this->respond(null, 'Workflow not found.', 404);
        }
        return $this->respond($workflow);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/workflows
    // -------------------------------------------------------------------------

    public function create(array $body): Response
    {
        $id = $this->service->create($body, (int) $this->userId);
        return $this->respond(['id' => $id], null, 201);
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/crm/workflows/{id}
    // -------------------------------------------------------------------------

    public function update(int $id, array $body): Response
    {
        $updated = $this->service->update($id, $body);
        if (!$updated) {
            return $this->respond(null, 'Workflow not found.', 404);
        }
        return $this->respond(['updated' => true]);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/crm/workflows/{id}
    // -------------------------------------------------------------------------

    public function delete(int $id): Response
    {
        $deleted = $this->service->delete($id);
        if (!$deleted) {
            return $this->respond(null, 'Workflow not found.', 404);
        }
        return $this->respond(['deleted' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/workflows/{id}/enable   — Requirement 14.8
    // POST /api/v1/crm/workflows/{id}/disable
    // -------------------------------------------------------------------------

    public function enable(int $id): Response
    {
        $ok = $this->service->setEnabled($id, true);
        if (!$ok) {
            return $this->respond(null, 'Workflow not found.', 404);
        }
        return $this->respond(['enabled' => true]);
    }

    public function disable(int $id): Response
    {
        $ok = $this->service->setEnabled($id, false);
        if (!$ok) {
            return $this->respond(null, 'Workflow not found.', 404);
        }
        return $this->respond(['enabled' => false]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/workflows/{id}/clone    — Requirement 14.8
    // -------------------------------------------------------------------------

    public function clone(int $id): Response
    {
        $newId = $this->service->clone($id, (int) $this->userId);
        return $this->respond(['id' => $newId], null, 201);
    }
}
