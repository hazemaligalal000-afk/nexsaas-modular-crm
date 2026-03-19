<?php
/**
 * CRM/Accounts/AccountController.php
 *
 * REST endpoints for account management.
 *
 * Routes:
 *   GET    /api/v1/crm/accounts                              → list()
 *   POST   /api/v1/crm/accounts                              → create()
 *   GET    /api/v1/crm/accounts/{id}                         → show()
 *   PUT    /api/v1/crm/accounts/{id}                         → update()
 *   DELETE /api/v1/crm/accounts/{id}                         → destroy()
 *   GET    /api/v1/crm/accounts/{id}/timeline                → timeline()
 *   GET    /api/v1/crm/accounts/{id}/contacts                → contacts()
 *   POST   /api/v1/crm/accounts/{id}/contacts/{contactId}    → linkContact()
 *   DELETE /api/v1/crm/accounts/{id}/contacts/{contactId}    → unlinkContact()
 *
 * Requirements: 9.1, 9.2, 9.3
 */

declare(strict_types=1);

namespace CRM\Accounts;

use Core\BaseController;
use Core\Response;

class AccountController extends BaseController
{
    private AccountService $service;

    public function __construct(AccountService $service)
    {
        $this->service = $service;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/accounts
    // -------------------------------------------------------------------------

    /**
     * List accounts for the current tenant.
     *
     * Query params: limit (default 50), offset (default 0)
     *
     * @param  array $queryParams
     * @return Response
     */
    public function list(array $queryParams = []): Response
    {
        try {
            $limit  = max(1, min(200, (int) ($queryParams['limit']  ?? 50)));
            $offset = max(0, (int) ($queryParams['offset'] ?? 0));
            $accounts = $this->service->list($limit, $offset);
            return $this->respond($accounts);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/accounts
    // -------------------------------------------------------------------------

    /**
     * Create a new account.
     *
     * @param  array $body
     * @param  int   $createdBy
     * @return Response
     */
    public function create(array $body, int $createdBy): Response
    {
        try {
            $id      = $this->service->create($body, $createdBy);
            $account = $this->service->findById($id);
            return $this->respond($account, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/accounts/{id}
    // -------------------------------------------------------------------------

    /**
     * Show a single account.
     *
     * @param  int $id
     * @return Response
     */
    public function show(int $id): Response
    {
        try {
            $account = $this->service->findById($id);

            if ($account === null) {
                return $this->respond(null, 'Account not found.', 404);
            }

            return $this->respond($account);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/crm/accounts/{id}
    // -------------------------------------------------------------------------

    /**
     * Update an account.
     *
     * @param  int   $id
     * @param  array $body
     * @return Response
     */
    public function update(int $id, array $body): Response
    {
        try {
            $updated = $this->service->update($id, $body);

            if (!$updated) {
                return $this->respond(null, 'Account not found or no changes made.', 404);
            }

            $account = $this->service->findById($id);
            return $this->respond($account);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/crm/accounts/{id}
    // -------------------------------------------------------------------------

    /**
     * Soft-delete an account.
     *
     * @param  int $id
     * @return Response
     */
    public function destroy(int $id): Response
    {
        try {
            $deleted = $this->service->delete($id);

            if (!$deleted) {
                return $this->respond(null, 'Account not found.', 404);
            }

            return $this->respond(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/accounts/{id}/timeline
    // -------------------------------------------------------------------------

    /**
     * Fetch the activity timeline for an account.
     *
     * @param  int $id
     * @return Response
     */
    public function timeline(int $id): Response
    {
        try {
            $account = $this->service->findById($id);

            if ($account === null) {
                return $this->respond(null, 'Account not found.', 404);
            }

            $timeline = $this->service->getTimeline($id);
            return $this->respond($timeline);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/accounts/{id}/contacts — Requirement 9.2
    // -------------------------------------------------------------------------

    /**
     * List all contacts linked to an account.
     *
     * @param  int $id
     * @return Response
     */
    public function contacts(int $id): Response
    {
        try {
            if ($this->service->findById($id) === null) {
                return $this->respond(null, 'Account not found.', 404);
            }

            return $this->respond($this->service->getContacts($id));
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/accounts/{id}/contacts/{contactId} — Requirement 9.2
    // -------------------------------------------------------------------------

    /**
     * Link a contact to an account.
     *
     * @param  int $id        Account ID
     * @param  int $contactId Contact ID
     * @param  int $userId    Authenticated user ID
     * @return Response
     */
    public function linkContact(int $id, int $contactId, int $userId): Response
    {
        try {
            $this->service->linkContact($id, $contactId, $userId);
            return $this->respond(['linked' => true], null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/crm/accounts/{id}/contacts/{contactId} — Requirement 9.2
    // -------------------------------------------------------------------------

    /**
     * Unlink a contact from an account.
     *
     * @param  int $id        Account ID
     * @param  int $contactId Contact ID
     * @return Response
     */
    public function unlinkContact(int $id, int $contactId): Response
    {
        try {
            $removed = $this->service->unlinkContact($id, $contactId);

            if (!$removed) {
                return $this->respond(null, 'Link not found.', 404);
            }

            return $this->respond(['unlinked' => true]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
