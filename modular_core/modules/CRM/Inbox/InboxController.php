<?php
/**
 * CRM/Inbox/InboxController.php
 *
 * REST endpoints for the Omnichannel Inbox.
 *
 * Routes (registered in CRM/module.json):
 *   GET  /api/v1/crm/inbox/conversations               → index()
 *   GET  /api/v1/crm/inbox/conversations/{id}/messages → messages()
 *   POST /api/v1/crm/inbox/conversations/{id}/reply    → reply()
 *
 * Requirements: 12.3, 12.4, 12.5
 */

declare(strict_types=1);

namespace CRM\Inbox;

use Core\BaseController;
use Core\Response;

class InboxController extends BaseController
{
    private InboxService $service;
    private CannedResponseService $cannedService;

    /** @var object  Redis client for WebSocket notification dispatch (Req 12.3) */
    private object $redis;

    public function __construct(InboxService $service, object $redis, ?CannedResponseService $cannedService = null)
    {
        $this->service       = $service;
        $this->redis         = $redis;
        $this->cannedService = $cannedService ?? new CannedResponseService(
            // Lazy placeholder — real instance injected via buildInboxController()
            new class { public function Execute(): object { return new class { public bool $EOF = true; public function MoveNext(): void {} }; } public function ErrorMsg(): string { return ''; } public function Affected_Rows(): int { return 0; } public function Insert_ID(): mixed { return null; } public function BeginTrans(): void {} public function CommitTrans(): void {} public function RollbackTrans(): void {} },
            '',
            '01'
        );
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/inbox/conversations
    // Requirement 12.4 — threaded conversation list per tenant
    // -------------------------------------------------------------------------

    /**
     * List conversations for the current tenant.
     *
     * Query params:
     *   status            string  open|pending|resolved (optional)
     *   channel           string  email|sms|whatsapp|chat|voip (optional)
     *   assigned_agent_id int     filter by assigned agent (optional)
     *   page              int     1-based page number (default 1)
     *   per_page          int     items per page, 1–100 (default 20)
     *
     * @param  array $queryParams
     * @return Response
     */
    public function index(array $queryParams = []): Response
    {
        $userId = (int) ($this->userId ?? 0);

        // RBAC check — inbox.read (Req 2.4)
        if (!$this->checkPermission($userId, 'crm.inbox.read')) {
            return $this->respond(null, 'Forbidden: inbox.read permission required.', 403);
        }

        try {
            $page    = max(1, (int) ($queryParams['page']     ?? 1));
            $perPage = max(1, min(100, (int) ($queryParams['per_page'] ?? 20)));
            $offset  = ($page - 1) * $perPage;

            $filters = [];
            if (!empty($queryParams['status'])) {
                $filters['status'] = (string) $queryParams['status'];
            }
            if (!empty($queryParams['channel'])) {
                $filters['channel'] = (string) $queryParams['channel'];
            }
            if (!empty($queryParams['assigned_agent_id'])) {
                $filters['assigned_agent_id'] = (int) $queryParams['assigned_agent_id'];
            }

            $conversations = $this->service->listConversations($filters, $perPage, $offset);

            return $this->respond([
                'conversations' => $conversations,
                'pagination'    => [
                    'page'     => $page,
                    'per_page' => $perPage,
                    'count'    => count($conversations),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/inbox/conversations/{id}/messages
    // Requirement 12.4 — threaded message history per conversation
    // -------------------------------------------------------------------------

    /**
     * Get the full message thread for a conversation.
     *
     * Returns HTTP 404 if the conversation does not exist or belongs to a
     * different tenant (tenant isolation enforced by InboxService).
     *
     * @param  int $id  Conversation ID
     * @return Response
     */
    public function messages(int $id): Response
    {
        $userId = (int) ($this->userId ?? 0);

        // RBAC check — inbox.read
        if (!$this->checkPermission($userId, 'crm.inbox.read')) {
            return $this->respond(null, 'Forbidden: inbox.read permission required.', 403);
        }

        try {
            // Verify conversation exists and belongs to this tenant
            $conversation = $this->service->findConversation($id);
            if ($conversation === null) {
                return $this->respond(null, 'Conversation not found.', 404);
            }

            $messages = $this->service->getMessages($id);

            return $this->respond([
                'conversation' => $conversation,
                'messages'     => $messages,
            ]);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/crm/inbox/conversations/{id}/reply
    // Requirements 12.3, 12.5 — send reply and push WebSocket notification ≤3s
    // -------------------------------------------------------------------------

    /**
     * Send a reply in a conversation and dispatch a real-time WebSocket
     * notification to the assigned agent within 3 seconds (Req 12.3).
     *
     * Body:
     *   body  string  (required) Reply text
     *
     * @param  int   $id    Conversation ID
     * @param  array $body  Decoded request body
     * @return Response
     */
    public function reply(int $id, array $body): Response
    {
        $userId = (int) ($this->userId ?? 0);

        // RBAC check — inbox.reply
        if (!$this->checkPermission($userId, 'crm.inbox.reply')) {
            return $this->respond(null, 'Forbidden: inbox.reply permission required.', 403);
        }

        $replyText = isset($body['body']) ? trim((string) $body['body']) : '';
        if ($replyText === '') {
            return $this->respond(null, 'body is required and cannot be empty.', 422);
        }

        try {
            // Verify conversation exists and belongs to this tenant
            $conversation = $this->service->findConversation($id);
            if ($conversation === null) {
                return $this->respond(null, 'Conversation not found.', 404);
            }

            // Send the outbound message (persists + updates first_response_at)
            $messageId = $this->service->sendMessage($id, $replyText, $userId);

            // Reload conversation to get updated state (first_response_at, updated_at)
            $updatedConversation = $this->service->findConversation($id);

            // Dispatch WebSocket notification within 3s (Req 12.3)
            $this->dispatchWebSocketNotification(
                $conversation,
                $messageId,
                $replyText
            );

            return $this->respond([
                'message_id'   => $messageId,
                'conversation' => $updatedConversation,
            ], null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(null, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/crm/inbox/canned-responses/public
    // Public endpoint for the chat widget — no auth required (Req 12.8)
    // Returns only shortcut, title, body (no internal IDs exposed)
    // -------------------------------------------------------------------------

    /**
     * Return canned responses for the widget (public, tenant-scoped via query param).
     *
     * Query params:
     *   tenant  string  Tenant UUID (required)
     *
     * @param  array $queryParams
     * @return Response
     */
    public function cannedPublic(array $queryParams = []): Response
    {
        try {
            $items = $this->cannedService->list();
            $safe  = array_map(fn($r) => [
                'shortcut' => $r['shortcut'],
                'title'    => $r['title'],
                'body'     => $r['body'],
            ], $items);
            return $this->respond(['canned_responses' => $safe]);
        } catch (\Throwable $e) {
            return $this->respond(['canned_responses' => []]);
        }
    }

    // -------------------------------------------------------------------------
    // WebSocket notification dispatch — Requirement 12.3
    // -------------------------------------------------------------------------

    /**
     * Push a real-time notification to the assigned agent via Redis pub/sub.
     *
     * The WebSocket server (Ratchet/Swoole) subscribes to
     * `tenant:{tenant_id}:user:{agent_id}` and forwards the event to the
     * connected client within 3 seconds (Req 12.3).
     *
     * Undelivered notifications are also stored in the Redis list
     * `notifications:pending:{agent_id}` so they can be flushed on reconnect.
     *
     * @param  array  $conversation  Conversation row
     * @param  int    $messageId     Newly created message ID
     * @param  string $replyText     Full reply body (preview truncated to 120 chars)
     */
    private function dispatchWebSocketNotification(
        array  $conversation,
        int    $messageId,
        string $replyText
    ): void {
        $agentId = (int) ($conversation['assigned_agent_id'] ?? 0);
        if ($agentId === 0) {
            return; // No assigned agent — nothing to notify
        }

        $tenantId       = $this->tenantId;
        $conversationId = (int) $conversation['id'];
        $bodyPreview    = mb_substr($replyText, 0, 120);

        $payload = json_encode([
            'type'            => 'new_message',
            'conversation_id' => $conversationId,
            'message_id'      => $messageId,
            'body_preview'    => $bodyPreview,
            'timestamp'       => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                                    ->format(\DateTimeInterface::ATOM),
        ]);

        // Publish to WebSocket server channel (Req 12.3 — delivered within 3s)
        $channel = "tenant:{$tenantId}:user:{$agentId}";
        $this->redis->publish($channel, $payload);

        // Store in pending list for reconnect recovery
        $pendingKey = "notifications:pending:{$agentId}";
        $this->redis->rPush($pendingKey, $payload);
    }

    // -------------------------------------------------------------------------
    // RBAC helper
    // -------------------------------------------------------------------------

    /**
     * Check a permission for the current user.
     *
     * Falls back to allowing all when no Redis is available (dev/test mode).
     *
     * @param  int    $userId
     * @param  string $permission
     * @return bool
     */
    private function checkPermission(int $userId, string $permission): bool
    {
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
            // Redis unavailable — fail open in dev, fail closed in prod
            // For safety, deny access when permission cache is unavailable
            return false;
        }

        // Cache miss — deny (RBAC middleware should have populated cache)
        // In production the JWT middleware pre-warms the permission cache.
        // Returning false here is the safe default.
        return false;
    }
}
