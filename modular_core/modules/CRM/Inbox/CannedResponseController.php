<?php
/**
 * CRM/Inbox/CannedResponseController.php
 *
 * REST endpoints for canned responses.
 *
 * Routes:
 *   GET    /api/v1/crm/inbox/canned-responses          → index()
 *   POST   /api/v1/crm/inbox/canned-responses          → create()
 *   PUT    /api/v1/crm/inbox/canned-responses/{id}     → update()
 *   DELETE /api/v1/crm/inbox/canned-responses/{id}     → destroy()
 *
 * Requirements: 12.8
 */

declare(strict_types=1);

namespace CRM\Inbox;

use Core\BaseController;
use Core\Response;

class CannedResponseController extends BaseController
{
    private CannedResponseService $service;
    private object $redis;

    public function __construct(CannedResponseService $service, object $redis)
    {
        $this->service = $service;
        $this->redis   = $redis;
    }

    /**
     * GET /api/v1/crm/inbox/canned-responses
     *
     * Query params:
     *   search  string  Optional substring filter on shortcut or title
     */
    public function index(array $queryParams = []): Response
    {
        if (!$this->checkPermission('crm.inbox.read')) {
            return $this->respond(null, 'Forbidden: crm.inbox.read permission required.', 403);
        }

        try {
            $search = trim((string) ($queryParams['search'] ?? ''));
            $items  = $this->service->list($search);
            return $this->respond(['canned_responses' => $items]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/crm/inbox/canned-responses
     *
     * Body: { shortcut, title, body }
     */
    public function create(array $body): Response
    {
        if (!$this->checkPermission('crm.inbox.manage')) {
            return $this->respond(null, 'Forbidden: crm.inbox.manage permission required.', 403);
        }

        try {
            $record = $this->service->create($body, (int) ($this->userId ?? 0));
            return $this->respond(['canned_response' => $record], null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/v1/crm/inbox/canned-responses/{id}
     *
     * Body: { shortcut?, title?, body? }
     */
    public function update(int $id, array $body): Response
    {
        if (!$this->checkPermission('crm.inbox.manage')) {
            return $this->respond(null, 'Forbidden: crm.inbox.manage permission required.', 403);
        }

        try {
            $record = $this->service->update($id, $body, (int) ($this->userId ?? 0));
            return $this->respond(['canned_response' => $record]);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/v1/crm/inbox/canned-responses/{id}
     */
    public function destroy(int $id): Response
    {
        if (!$this->checkPermission('crm.inbox.manage')) {
            return $this->respond(null, 'Forbidden: crm.inbox.manage permission required.', 403);
        }

        try {
            $deleted = $this->service->delete($id, (int) ($this->userId ?? 0));
            if (!$deleted) {
                return $this->respond(null, 'Canned response not found.', 404);
            }
            return $this->respond(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // RBAC helper
    // -------------------------------------------------------------------------

    private function checkPermission(string $permission): bool
    {
        $userId = (int) ($this->userId ?? 0);
        if ($userId === 0) {
            return false;
        }

        try {
            $cacheKey = "permissions:{$this->tenantId}:{$userId}";
            $cached   = $this->redis->get($cacheKey);
            if ($cached !== false && $cached !== null) {
                $permissions = json_decode($cached, true);
                if (is_array($permissions)) {
                    return in_array($permission, $permissions, true);
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
