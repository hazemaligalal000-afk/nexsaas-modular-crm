<?php
/**
 * CRM/Inbox/InboxService.php
 *
 * Aggregates inbound and outbound messages from email (IMAP/SMTP), SMS (Twilio),
 * WhatsApp (Meta API), live chat (WebSocket), and VoIP (SIP) into a single
 * Inbox per Tenant.
 *
 * Auto-links each conversation to a Contact or Lead record when the sender's
 * email or phone matches an existing record.
 *
 * Requirements: 12.1, 12.2, 12.6
 */

declare(strict_types=1);

namespace CRM\Inbox;

use Core\BaseService;
use CRM\Inbox\Channels\EmailChannel;
use CRM\Inbox\Channels\SmsChannel;
use CRM\Inbox\Channels\WhatsAppChannel;
use CRM\Inbox\Channels\LiveChatChannel;
use CRM\Inbox\Channels\VoipChannel;

class InboxService extends BaseService
{
    private string $tenantId;
    private string $companyCode;
    private ConversationMetricsService $metricsService;

    /** @var array<string, object> Registered channel handlers */
    private array $channels = [];

    public function __construct($db, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->tenantId       = $tenantId;
        $this->companyCode    = $companyCode;
        $this->metricsService = new ConversationMetricsService($db, $tenantId, $companyCode);
    }

    // -------------------------------------------------------------------------
    // Channel registration
    // -------------------------------------------------------------------------

    /**
     * Register a channel handler. Called during module bootstrap.
     *
     * @param string $channel  One of: email|sms|whatsapp|chat|voip
     * @param object $handler  Channel handler instance
     */
    public function registerChannel(string $channel, object $handler): void
    {
        $this->channels[$channel] = $handler;
    }

    /**
     * Build and register all default channel handlers.
     *
     * @param array $config  Channel-specific configuration keyed by channel name
     */
    public function registerDefaultChannels(array $config = []): void
    {
        $this->channels['email']     = new EmailChannel($config['email'] ?? []);
        $this->channels['sms']       = new SmsChannel($config['sms'] ?? []);
        $this->channels['whatsapp']  = new WhatsAppChannel($config['whatsapp'] ?? []);
        $this->channels['chat']      = new LiveChatChannel($config['chat'] ?? []);
        $this->channels['voip']      = new VoipChannel($config['voip'] ?? []);
    }

    // -------------------------------------------------------------------------
    // Inbound message receipt — Requirement 12.1, 12.2
    // -------------------------------------------------------------------------

    /**
     * Handle an inbound message from any channel.
     *
     * 1. Resolves or creates a conversation for the sender.
     * 2. Auto-links the conversation to a Contact or Lead (Req 12.2).
     * 3. Persists the message.
     * 4. Updates conversation metrics (Req 12.6).
     *
     * @param  string $channel       One of: email|sms|whatsapp|chat|voip
     * @param  array  $messageData   Normalised message payload:
     *                               - sender_email (string|null)
     *                               - sender_phone (string|null)
     *                               - body         (string)
     *                               - external_id  (string|null)  channel-specific message ID
     *                               - metadata     (array)        channel-specific extras
     * @param  int    $createdBy     User ID (0 for system/webhook)
     * @return array                 ['conversation_id' => int, 'message_id' => int]
     *
     * @throws \InvalidArgumentException on unsupported channel or missing body
     */
    public function receiveMessage(string $channel, array $messageData, int $createdBy = 0): array
    {
        $this->assertValidChannel($channel);

        $body = trim($messageData['body'] ?? '');
        if ($body === '') {
            throw new \InvalidArgumentException('Message body cannot be empty.');
        }

        $senderEmail = isset($messageData['sender_email']) && $messageData['sender_email'] !== ''
            ? trim(strtolower($messageData['sender_email']))
            : null;

        $senderPhone = isset($messageData['sender_phone']) && $messageData['sender_phone'] !== ''
            ? trim($messageData['sender_phone'])
            : null;

        return $this->transaction(function () use ($channel, $body, $senderEmail, $senderPhone, $messageData, $createdBy): array {
            // Resolve or create conversation
            $conversationId = $this->resolveConversation($channel, $senderEmail, $senderPhone, $createdBy);

            // Auto-link to Contact or Lead (Req 12.2)
            $this->autoLinkConversation($conversationId, $senderEmail, $senderPhone);

            // Persist the message
            $messageId = $this->insertMessage($conversationId, 'inbound', $body, $createdBy);

            // Track first_response_at if this is the first inbound message
            $this->metricsService->onInboundMessage($conversationId);

            return ['conversation_id' => $conversationId, 'message_id' => $messageId];
        });
    }

    // -------------------------------------------------------------------------
    // Outbound message sending — Requirement 12.1, 12.5
    // -------------------------------------------------------------------------

    /**
     * Send an outbound message on a conversation.
     *
     * Dispatches via the appropriate channel handler and records the message.
     * Updates first_response_at if this is the first agent reply (Req 12.6).
     *
     * @param  int    $conversationId
     * @param  string $body
     * @param  int    $agentId         Authenticated agent user ID
     * @return int                     New message ID
     *
     * @throws \RuntimeException if conversation not found
     */
    public function sendMessage(int $conversationId, string $body, int $agentId): int
    {
        $conversation = $this->findConversationById($conversationId);
        if ($conversation === null) {
            throw new \RuntimeException("Conversation {$conversationId} not found.");
        }

        $channel = $conversation['channel'];
        $this->assertValidChannel($channel);

        // Dispatch via channel handler if registered
        if (isset($this->channels[$channel])) {
            $this->channels[$channel]->send($conversation, $body);
        }

        return $this->transaction(function () use ($conversationId, $body, $agentId, $conversation): int {
            $messageId = $this->insertMessage($conversationId, 'outbound', $body, $agentId);

            // Track first_response_at (Req 12.6)
            $this->metricsService->onOutboundMessage($conversationId, $conversation);

            return $messageId;
        });
    }

    // -------------------------------------------------------------------------
    // Conversation resolution — Requirement 12.6
    // -------------------------------------------------------------------------

    /**
     * Mark a conversation as resolved.
     *
     * Sets resolved_at and computes handle_time (Req 12.6).
     *
     * @param  int $conversationId
     * @param  int $agentId
     * @return bool
     */
    public function resolveConversation(int $conversationId, int $agentId): bool
    {
        $conversation = $this->findConversationById($conversationId);
        if ($conversation === null) {
            throw new \RuntimeException("Conversation {$conversationId} not found.");
        }

        $this->metricsService->onResolved($conversationId, $conversation);

        $now = $this->now();
        $result = $this->db->Execute(
            'UPDATE inbox_conversations SET status = ?, resolved_at = ?, updated_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            ['resolved', $now, $now, $conversationId, $this->tenantId, $this->companyCode]
        );

        if ($result === false) {
            throw new \RuntimeException('InboxService::resolveConversation failed: ' . $this->db->ErrorMsg());
        }

        return $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // Conversation queries
    // -------------------------------------------------------------------------

    /**
     * List conversations for the current tenant, optionally filtered.
     *
     * @param  array $filters  Optional: channel, status, contact_id, lead_id, assigned_agent_id
     * @param  int   $limit
     * @param  int   $offset
     * @return array
     */
    public function listConversations(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['tenant_id = ?', 'company_code = ?', 'deleted_at IS NULL'];
        $params = [$this->tenantId, $this->companyCode];

        foreach (['channel', 'status', 'contact_id', 'lead_id', 'assigned_agent_id'] as $field) {
            if (!empty($filters[$field])) {
                $where[]  = "{$field} = ?";
                $params[] = $filters[$field];
            }
        }

        $sql = 'SELECT * FROM inbox_conversations WHERE ' . implode(' AND ', $where)
             . ' ORDER BY updated_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $rs = $this->db->Execute($sql, $params);
        if ($rs === false) {
            throw new \RuntimeException('InboxService::listConversations failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }
        return $rows;
    }

    /**
     * Get all messages for a conversation (threaded view — Req 12.4).
     *
     * @param  int $conversationId
     * @return array
     */
    public function getMessages(int $conversationId): array
    {
        $rs = $this->db->Execute(
            'SELECT * FROM inbox_messages WHERE conversation_id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL ORDER BY created_at ASC',
            [$conversationId, $this->tenantId, $this->companyCode]
        );

        if ($rs === false) {
            throw new \RuntimeException('InboxService::getMessages failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }
        return $rows;
    }

    /**
     * Get conversation metrics summary (Req 12.6).
     *
     * @param  int $conversationId
     * @return array
     */
    public function getMetrics(int $conversationId): array
    {
        return $this->metricsService->getMetrics($conversationId);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Find or create a conversation for the given channel + sender.
     *
     * Reuses an existing open/pending conversation for the same sender on the
     * same channel. Creates a new one if none exists.
     *
     * @param  string      $channel
     * @param  string|null $senderEmail
     * @param  string|null $senderPhone
     * @param  int         $createdBy
     * @return int  Conversation ID
     */
    private function resolveConversation(string $channel, ?string $senderEmail, ?string $senderPhone, int $createdBy): int
    {
        // Try to find an existing open conversation for this sender on this channel
        $existing = $this->findOpenConversation($channel, $senderEmail, $senderPhone);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        // Create a new conversation
        $now = $this->now();
        $rs  = $this->db->Execute(
            'INSERT INTO inbox_conversations (tenant_id, company_code, channel, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id',
            [$this->tenantId, $this->companyCode, $channel, 'open', $createdBy ?: null, $now, $now]
        );

        if ($rs === false) {
            throw new \RuntimeException('InboxService: failed to create conversation: ' . $this->db->ErrorMsg());
        }

        return (!$rs->EOF) ? (int) $rs->fields['id'] : (int) $this->db->Insert_ID();
    }

    /**
     * Find an open or pending conversation for the given sender on the channel.
     *
     * Matches by contact_id or lead_id derived from sender email/phone.
     *
     * @return array|null
     */
    private function findOpenConversation(string $channel, ?string $senderEmail, ?string $senderPhone): ?array
    {
        // First resolve contact/lead IDs for the sender
        $contactId = null;
        $leadId    = null;

        if ($senderEmail !== null || $senderPhone !== null) {
            [$contactId, $leadId] = $this->lookupContactOrLead($senderEmail, $senderPhone);
        }

        if ($contactId === null && $leadId === null) {
            return null; // No known sender — always create new conversation
        }

        $conditions = ['tenant_id = ?', 'company_code = ?', 'channel = ?', "status IN ('open','pending')", 'deleted_at IS NULL'];
        $params     = [$this->tenantId, $this->companyCode, $channel];

        if ($contactId !== null) {
            $conditions[] = 'contact_id = ?';
            $params[]     = $contactId;
        } elseif ($leadId !== null) {
            $conditions[] = 'lead_id = ?';
            $params[]     = $leadId;
        }

        $rs = $this->db->Execute(
            'SELECT * FROM inbox_conversations WHERE ' . implode(' AND ', $conditions) . ' ORDER BY updated_at DESC LIMIT 1',
            $params
        );

        if ($rs === false || $rs->EOF) {
            return null;
        }

        return $rs->fields;
    }

    /**
     * Auto-link a conversation to a Contact or Lead by sender email/phone.
     * Requirement 12.2
     *
     * @param  int         $conversationId
     * @param  string|null $senderEmail
     * @param  string|null $senderPhone
     */
    private function autoLinkConversation(int $conversationId, ?string $senderEmail, ?string $senderPhone): void
    {
        if ($senderEmail === null && $senderPhone === null) {
            return;
        }

        // Check if already linked
        $rs = $this->db->Execute(
            'SELECT contact_id, lead_id FROM inbox_conversations WHERE id = ? AND tenant_id = ? AND company_code = ?',
            [$conversationId, $this->tenantId, $this->companyCode]
        );

        if ($rs === false || $rs->EOF) {
            return;
        }

        $row = $rs->fields;
        if ($row['contact_id'] !== null || $row['lead_id'] !== null) {
            return; // Already linked
        }

        [$contactId, $leadId] = $this->lookupContactOrLead($senderEmail, $senderPhone);

        if ($contactId === null && $leadId === null) {
            return; // No match found
        }

        $now    = $this->now();
        $update = [];
        $params = [];

        if ($contactId !== null) {
            $update[] = 'contact_id = ?';
            $params[] = $contactId;
        }
        if ($leadId !== null) {
            $update[] = 'lead_id = ?';
            $params[] = $leadId;
        }

        $update[] = 'updated_at = ?';
        $params[] = $now;
        $params[] = $conversationId;
        $params[] = $this->tenantId;
        $params[] = $this->companyCode;

        $this->db->Execute(
            'UPDATE inbox_conversations SET ' . implode(', ', $update) . ' WHERE id = ? AND tenant_id = ? AND company_code = ?',
            $params
        );
    }

    /**
     * Look up a Contact or Lead by sender email or phone within this tenant.
     * Returns [contactId|null, leadId|null].
     *
     * @param  string|null $email
     * @param  string|null $phone
     * @return array{0: int|null, 1: int|null}
     */
    private function lookupContactOrLead(?string $email, ?string $phone): array
    {
        $contactId = null;
        $leadId    = null;

        // Try contacts first
        if ($email !== null || $phone !== null) {
            $conditions = ['tenant_id = ?', 'company_code = ?', 'deleted_at IS NULL'];
            $params     = [$this->tenantId, $this->companyCode];
            $orClauses  = [];

            if ($email !== null) {
                $orClauses[] = 'LOWER(email) = ?';
                $params[]    = $email;
            }
            if ($phone !== null) {
                $orClauses[] = 'phone = ?';
                $params[]    = $phone;
            }

            $conditions[] = '(' . implode(' OR ', $orClauses) . ')';

            $rs = $this->db->Execute(
                'SELECT id FROM contacts WHERE ' . implode(' AND ', $conditions) . ' LIMIT 1',
                $params
            );

            if ($rs !== false && !$rs->EOF) {
                $contactId = (int) $rs->fields['id'];
            }
        }

        // If no contact found, try leads
        if ($contactId === null && ($email !== null || $phone !== null)) {
            $conditions = ['tenant_id = ?', 'company_code = ?', 'deleted_at IS NULL'];
            $params     = [$this->tenantId, $this->companyCode];
            $orClauses  = [];

            if ($email !== null) {
                $orClauses[] = 'LOWER(email) = ?';
                $params[]    = $email;
            }
            if ($phone !== null) {
                $orClauses[] = 'phone = ?';
                $params[]    = $phone;
            }

            $conditions[] = '(' . implode(' OR ', $orClauses) . ')';

            $rs = $this->db->Execute(
                'SELECT id FROM leads WHERE ' . implode(' AND ', $conditions) . ' LIMIT 1',
                $params
            );

            if ($rs !== false && !$rs->EOF) {
                $leadId = (int) $rs->fields['id'];
            }
        }

        return [$contactId, $leadId];
    }

    /**
     * Insert a message record.
     *
     * @param  int    $conversationId
     * @param  string $direction  inbound|outbound
     * @param  string $body
     * @param  int    $createdBy
     * @return int    New message ID
     */
    private function insertMessage(int $conversationId, string $direction, string $body, int $createdBy): int
    {
        $now = $this->now();
        $rs  = $this->db->Execute(
            'INSERT INTO inbox_messages (tenant_id, company_code, conversation_id, direction, body, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id',
            [$this->tenantId, $this->companyCode, $conversationId, $direction, $body, $createdBy ?: null, $now, $now]
        );

        if ($rs === false) {
            throw new \RuntimeException('InboxService: failed to insert message: ' . $this->db->ErrorMsg());
        }

        // Update conversation updated_at
        $this->db->Execute(
            'UPDATE inbox_conversations SET updated_at = ? WHERE id = ? AND tenant_id = ? AND company_code = ?',
            [$now, $conversationId, $this->tenantId, $this->companyCode]
        );

        return (!$rs->EOF) ? (int) $rs->fields['id'] : (int) $this->db->Insert_ID();
    }

    /**
     * Public accessor: find a conversation by ID scoped to this tenant.
     *
     * Returns null if not found or belongs to a different tenant (Req 1.4).
     *
     * @param  int        $id
     * @return array|null
     */
    public function findConversation(int $id): ?array
    {
        return $this->findConversationById($id);
    }

    /**
     * Find a conversation by ID scoped to this tenant.
     */
    private function findConversationById(int $id): ?array
    {
        $rs = $this->db->Execute(
            'SELECT * FROM inbox_conversations WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL',
            [$id, $this->tenantId, $this->companyCode]
        );

        if ($rs === false || $rs->EOF) {
            return null;
        }

        return $rs->fields;
    }

    /**
     * Assert that the given channel name is valid.
     *
     * @throws \InvalidArgumentException
     */
    private function assertValidChannel(string $channel): void
    {
        $valid = ['email', 'sms', 'whatsapp', 'chat', 'voip'];
        if (!in_array($channel, $valid, true)) {
            throw new \InvalidArgumentException(
                "Unsupported channel '{$channel}'. Valid channels: " . implode(', ', $valid)
            );
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
