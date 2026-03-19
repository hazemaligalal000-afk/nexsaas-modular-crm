<?php
/**
 * CTI/CallLogService.php
 *
 * Create and update call_log rows, handle recording storage references.
 */

declare(strict_types=1);

namespace CTI;

use Core\BaseService;

class CallLogService extends BaseService
{
    private string $tenantId;
    private string $companyCode;

    public function __construct($db, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    /**
     * Create a new call log entry when a call is initiated.
     */
    public function create(array $data): int
    {
        $now = $this->now();
        $rs  = $this->db->Execute(
            'INSERT INTO call_log (
                tenant_id, company_code, platform, call_sid, parent_call_sid,
                direction, from_number, to_number, did_number,
                contact_id, company_id, deal_id, ticket_id,
                agent_id, queue_name, ivr_path,
                initiated_at, status, raw_platform_data, created_at, updated_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id',
            [
                $this->tenantId,
                $this->companyCode,
                $data['platform']         ?? 'twilio',
                $data['call_sid'],
                $data['parent_call_sid']  ?? null,
                $data['direction']        ?? 'inbound',
                $data['from_number'],
                $data['to_number'],
                $data['did_number']       ?? null,
                $data['contact_id']       ?? null,
                $data['company_id']       ?? null,
                $data['deal_id']          ?? null,
                $data['ticket_id']        ?? null,
                $data['agent_id']         ?? 'system',
                $data['queue_name']       ?? null,
                $data['ivr_path']         ?? null,
                $data['initiated_at']     ?? $now,
                $data['status']           ?? 'initiated',
                isset($data['raw']) ? json_encode($data['raw']) : null,
                $now,
                $now,
            ]
        );

        if ($rs === false) {
            throw new \RuntimeException('CallLogService::create failed: ' . $this->db->ErrorMsg());
        }

        return (!$rs->EOF) ? (int)$rs->fields['id'] : (int)$this->db->Insert_ID();
    }

    /**
     * Update call status (answered, ended, etc.)
     */
    public function updateStatus(string $callSid, string $status, array $extra = []): bool
    {
        $set    = ['status = ?', 'updated_at = ?'];
        $params = [$status, $this->now()];

        $allowed = ['answered_at', 'ended_at', 'disposition_code', 'disposition_notes',
                    'recording_url', 'recording_s3_key', 'recording_duration', 'recording_status',
                    'agent_id', 'queue_name', 'contact_id', 'deal_id'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $extra)) {
                $set[]    = "{$field} = ?";
                $params[] = $extra[$field];
            }
        }

        $params[] = $callSid;
        $params[] = $this->tenantId;

        $rs = $this->db->Execute(
            'UPDATE call_log SET ' . implode(', ', $set) . ' WHERE call_sid = ? AND tenant_id = ?',
            $params
        );

        return $rs !== false && $this->db->Affected_Rows() > 0;
    }

    /**
     * Update AI analysis fields after transcription.
     */
    public function updateTranscript(string $callSid, array $ai): bool
    {
        $rs = $this->db->Execute(
            'UPDATE call_log SET
                transcript_text = ?, transcript_ar = ?, transcript_en = ?,
                ai_summary = ?, ai_action_items = ?, ai_sentiment = ?,
                ai_intent = ?, ai_keywords = ?, transcript_status = ?, updated_at = ?
             WHERE call_sid = ? AND tenant_id = ?',
            [
                $ai['transcript_text']  ?? null,
                $ai['transcript_ar']    ?? null,
                $ai['transcript_en']    ?? null,
                $ai['summary']          ?? null,
                isset($ai['action_items']) ? json_encode($ai['action_items']) : null,
                $ai['sentiment']        ?? null,
                $ai['intent']           ?? null,
                isset($ai['keywords']) ? json_encode($ai['keywords']) : null,
                'completed',
                $this->now(),
                $callSid,
                $this->tenantId,
            ]
        );

        return $rs !== false;
    }

    /**
     * Get a call log entry by call_sid.
     */
    public function getBySid(string $callSid): ?array
    {
        $rs = $this->db->Execute(
            'SELECT * FROM call_log WHERE call_sid = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$callSid, $this->tenantId]
        );

        if ($rs === false || $rs->EOF) {
            return null;
        }

        return $rs->fields;
    }

    /**
     * List call logs with optional filters.
     */
    public function list(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['tenant_id = ?', 'company_code = ?', 'deleted_at IS NULL'];
        $params = [$this->tenantId, $this->companyCode];

        foreach (['agent_id', 'status', 'direction', 'contact_id', 'platform'] as $f) {
            if (!empty($filters[$f])) {
                $where[]  = "{$f} = ?";
                $params[] = $filters[$f];
            }
        }

        if (!empty($filters['date_from'])) {
            $where[]  = 'initiated_at >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'initiated_at <= ?';
            $params[] = $filters['date_to'];
        }

        $sql = 'SELECT * FROM call_log WHERE ' . implode(' AND ', $where)
             . ' ORDER BY initiated_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $rs = $this->db->Execute($sql, $params);
        if ($rs === false) {
            throw new \RuntimeException('CallLogService::list failed: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }
        return $rows;
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
