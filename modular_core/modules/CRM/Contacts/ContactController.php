<?php
/**
 * CRM/Contacts/ContactController.php
 *
 * REST endpoints for contact management.
 *
 * Routes:
 *   GET    /api/v1/crm/contacts              → list()
 *   POST   /api/v1/crm/contacts              → create()
 *   GET    /api/v1/crm/contacts/{id}         → show()
 *   PUT    /api/v1/crm/contacts/{id}         → update()
 *   DELETE /api/v1/crm/contacts/{id}         → destroy()
 *   POST   /api/v1/crm/contacts/{id}/merge   → merge()
 *   GET    /api/v1/crm/contacts/{id}/timeline → timeline()
 *
 * Requirements: 6.1, 6.4, 6.6
 */

declare(strict_types=1);

namespace CRM\Contacts;

use Core\BaseController;
use Core\Response;

class ContactController extends BaseController
{
    private ContactService $service;

    public function __construct(ContactService $service)
    {
        $this->service = $service;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/contacts
    // -------------------------------------------------------------------------

    /**
     * List contacts for the current tenant.
     *
     * Query params: limit (default 50), offset (default 0), q (search query)
     *
     * @param  array $queryParams  Parsed query string parameters
     * @return Response
     */
    public function list(array $queryParams = []): Response
    {
        try {
            $q = isset($queryParams['q']) ? trim((string) $queryParams['q']) : '';

            if ($q !== '') {
                $contacts = $this->service->search($q);
            } else {
                $limit  = max(1, min(200, (int) ($queryParams['limit']  ?? 50)));
                $offset = max(0, (int) ($queryParams['offset'] ?? 0));
                $contacts = $this->service->list($limit, $offset);
            }

            return $this->respond($contacts);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/contacts
    // -------------------------------------------------------------------------

    /**
     * Create a new contact.
     *
     * @param  array $body       Decoded request body
     * @param  int   $createdBy  Authenticated user ID
     * @return Response
     */
    public function create(array $body, int $createdBy): Response
    {
        try {
            $id = $this->service->create($body, $createdBy);
            $contact = $this->service->findById($id);
            return $this->respond($contact, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->respond(null, $e->getMessage(), 409);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/contacts/{id}
    // -------------------------------------------------------------------------

    /**
     * Show a single contact.
     *
     * @param  int $id
     * @return Response
     */
    public function show(int $id): Response
    {
        try {
            $contact = $this->service->findById($id);

            if ($contact === null) {
                return $this->respond(null, 'Contact not found.', 404);
            }

            return $this->respond($contact);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/crm/contacts/{id}
    // -------------------------------------------------------------------------

    /**
     * Update a contact.
     *
     * @param  int   $id
     * @param  array $body  Fields to update
     * @return Response
     */
    public function update(int $id, array $body): Response
    {
        try {
            $updated = $this->service->update($id, $body);

            if (!$updated) {
                return $this->respond(null, 'Contact not found or no changes made.', 404);
            }

            $contact = $this->service->findById($id);
            return $this->respond($contact);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->respond(null, $e->getMessage(), 409);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/crm/contacts/{id}
    // -------------------------------------------------------------------------

    /**
     * Soft-delete a contact.
     *
     * @param  int $id
     * @return Response
     */
    public function destroy(int $id): Response
    {
        try {
            $deleted = $this->service->delete($id);

            if (!$deleted) {
                return $this->respond(null, 'Contact not found.', 404);
            }

            return $this->respond(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/contacts/{id}/merge
    // -------------------------------------------------------------------------

    /**
     * Merge a duplicate contact into this contact (survivor).
     *
     * Body: { "duplicate_id": int }
     *
     * @param  int   $survivorId
     * @param  array $body
     * @return Response
     */
    public function merge(int $survivorId, array $body): Response
    {
        $duplicateId = isset($body['duplicate_id']) ? (int) $body['duplicate_id'] : 0;

        if ($duplicateId <= 0) {
            return $this->respond(null, 'duplicate_id is required and must be a positive integer.', 422);
        }

        try {
            $this->service->merge($survivorId, $duplicateId);
            $survivor = $this->service->findById($survivorId);
            return $this->respond($survivor);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/contacts/{id}/timeline
    // -------------------------------------------------------------------------

    /**
     * Fetch the activity timeline for a contact.
     *
     * @param  int $id
     * @return Response
     */
    public function timeline(int $id): Response
    {
        try {
            $contact = $this->service->findById($id);

            if ($contact === null) {
                return $this->respond(null, 'Contact not found.', 404);
            }

            $timeline = $this->service->getTimeline($id);
            return $this->respond($timeline);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
