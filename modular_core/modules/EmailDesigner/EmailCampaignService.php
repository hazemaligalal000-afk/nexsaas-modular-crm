<?php
/**
 * EmailDesigner/EmailCampaignService.php
 *
 * Campaign management and bulk sending.
 */

declare(strict_types=1);

namespace EmailDesigner;

use Core\BaseService;

class EmailCampaignService extends BaseService
{
    private string $tenantId;
    private string $companyCode;
    private $queue;

    public function __construct($db, $queue, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
        $this->queue       = $queue;
    }

    /**
     * Create a new email campaign.
     */
    public function create(array $data, int $createdBy): int
    {
        $this->validate($data);

        $now = $this->now();
        $rs  = $this->db->Execute(
            'INSERT INTO email_campaigns (
                tenant_id, company_code, name, template_id, segment_id,
                subject_en, subject_ar, from_name, from_email, reply_to,
                scheduled_at, status, created_by, created_at, updated_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id',
            [
                $this->tenantId, $this->companyCode, $data['name'],
                $data['template_id'], $data['segment_id'] ?? null,
                $data['subject_en'], $data['subject_ar'] ?? null,
                $data['from_name'], $data['from_email'], $data['reply_to'] ?? null,
                $data['scheduled_at'] ?? null, 'draft', $createdBy, $now, $now,
            ]
        );

        if ($rs === false) {
            throw new \RuntimeException('EmailCampaignService::create failed: ' . $this->db->ErrorMsg());
        }

        return (!$rs->EOF) ? (int)$rs->fields['id'] : (int)$this->db->Insert_ID();
    }

    /**
     * Schedule campaign for sending.
     */
    public function schedule(int $id, string $scheduledAt): bool
    {
        $rs = $this->db->Execute(
            'UPDATE email_campaigns SET status = ?, scheduled_at = ?, updated_at = ? WHERE id = ? AND tenant_id = ? AND status = ?',
            ['scheduled', $scheduledAt, $this->now(), $id, $this->tenantId, 'draft']
        );

        return $rs !== false && $this->db->Affected_Rows() > 0;
    }

    private function validate(array $data): void
    {
        if (empty($data['name']) || empty($data['template_id']) || empty($data['subject_en'])) {
            throw new \InvalidArgumentException('Required fields missing');
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
