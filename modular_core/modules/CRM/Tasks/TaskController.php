<?php
/**
 * CRM/Tasks/TaskController.php
 *
 * REST endpoints for task management.
 *
 * Routes:
 *   GET    /api/v1/crm/tasks                    → index()
 *   POST   /api/v1/crm/tasks                    → store()
 *   GET    /api/v1/crm/tasks/{id}               → show()
 *   PUT    /api/v1/crm/tasks/{id}               → update()
 *   POST   /api/v1/crm/tasks/{id}/complete      → complete()
 *   DELETE /api/v1/crm/tasks/{id}               → destroy()
 *   POST   /api/v1/crm/tasks/bulk-assign        → bulkAssign()
 *
 * Requirements: 15.1, 15.3, 15.4, 15.5, 15.6
 */

declare(strict_types=1);

namespace CRM\Tasks;

use Core\BaseController;
use Core\Response;

class TaskController extends BaseController
{
    private TaskService $service;

    public function __construct(TaskService $service)
    {
        $this->service = $service;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/tasks
    // -------------------------------------------------------------------------

    /**
     * List tasks for the current authenticated user.
     *
     * Query params: status, priority, sort (due_date|priority|created_at),
     *               dir (ASC|DESC), limit, offset
     *
     * Requirement 15.4
     *
     * @param  array $queryParams
     * @return Response
     */
    public function index(array $queryParams = []): Response
    {
        try {
            $userId = (int) $this->userId;
            $tasks  = $this->service->getForUser($userId, $this->tenantId, $queryParams);
            return $this->respond($tasks);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/tasks
    // -------------------------------------------------------------------------

    /**
     * Create a new task.
     *
     * Body: title, due_date, assigned_user_id, linked_record_type,
     *       linked_record_id, description?, priority?
     *
     * Requirement 15.1
     *
     * @param  array $body
     * @return Response
     */
    public function store(array $body): Response
    {
        try {
            $body['created_by'] = (int) $this->userId;
            $task = $this->service->create($body);
            return $this->respond($task, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/tasks/{id}
    // -------------------------------------------------------------------------

    /**
     * Show a single task.
     *
     * @param  int $id
     * @return Response
     */
    public function show(int $id): Response
    {
        try {
            $task = $this->service->findById($id);

            if ($task === null) {
                return $this->respond(null, 'Task not found.', 404);
            }

            return $this->respond($task);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/crm/tasks/{id}
    // -------------------------------------------------------------------------

    /**
     * Update a task.
     *
     * @param  int   $id
     * @param  array $body
     * @return Response
     */
    public function update(int $id, array $body): Response
    {
        try {
            $task = $this->service->update($id, $body, $this->tenantId);

            if (empty($task)) {
                return $this->respond(null, 'Task not found.', 404);
            }

            return $this->respond($task);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/tasks/{id}/complete
    // -------------------------------------------------------------------------

    /**
     * Mark a task as completed and log the activity to the timeline.
     *
     * Requirement 15.5
     *
     * @param  int $id
     * @return Response
     */
    public function complete(int $id): Response
    {
        try {
            $userId = (int) $this->userId;
            $task   = $this->service->complete($id, $this->tenantId, $userId);
            return $this->respond($task);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/crm/tasks/{id}
    // -------------------------------------------------------------------------

    /**
     * Soft-delete a task.
     *
     * @param  int $id
     * @return Response
     */
    public function destroy(int $id): Response
    {
        try {
            $deleted = $this->service->delete($id, $this->tenantId);

            if (!$deleted) {
                return $this->respond(null, 'Task not found.', 404);
            }

            return $this->respond(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/tasks/bulk-assign
    // -------------------------------------------------------------------------

    /**
     * Bulk reassign tasks to a new user. Requires Manager role or above.
     *
     * Body: { task_ids: int[], new_user_id: int }
     *
     * Requirement 15.6
     *
     * @param  array $body
     * @return Response
     */
    public function bulkAssign(array $body): Response
    {
        $taskIds   = $body['task_ids']   ?? [];
        $newUserId = isset($body['new_user_id']) ? (int) $body['new_user_id'] : 0;

        if (empty($taskIds) || !is_array($taskIds)) {
            return $this->respond(null, 'task_ids must be a non-empty array.', 422);
        }

        if ($newUserId <= 0) {
            return $this->respond(null, 'new_user_id is required.', 422);
        }

        try {
            $actingUserId = (int) $this->userId;
            $result = $this->service->bulkAssign($taskIds, $newUserId, $this->tenantId, $actingUserId);
            return $this->respond($result);
        } catch (\RuntimeException $e) {
            // Permission denied or DB error
            $message = $e->getMessage();
            $status  = str_contains($message, 'Manager role') ? 403 : 500;
            return $this->respond(null, $message, $status);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
