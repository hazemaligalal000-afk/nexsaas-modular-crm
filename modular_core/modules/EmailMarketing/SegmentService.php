<?php
/**
 * EmailMarketing/SegmentService.php
 * 
 * CORE → ADVANCED: High-Volume Email Segmentation & Deliverability
 */

declare(strict_types=1);

namespace Modules\EmailMarketing;

use Core\BaseService;

class SegmentService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Dispatch high-volume email blast to a dynamic segment
     * Rule: Automated Bounce & Unsubscribe filtering
     */
    public function dispatchBlast(int $campaignId, string $tenantId): array
    {
        // 1. Fetch Segment & Campaign
        $sql = "SELECT c.id, s.query_sql, c.template_id 
                FROM email_campaigns c
                JOIN segments s ON c.segment_id = s.id
                WHERE c.id = ? AND c.tenant_id = ? AND c.status = 'ready'";
        
        $campaign = $this->db->GetRow($sql, [$campaignId, $tenantId]);

        if (!$campaign) throw new \RuntimeException("Campaign not found or status not ready.");

        // 2. Automated Deliverability Filtering (Batch Email-B)
        // Rule: Exclude bounced or unsubscribed emails from the target list
        $cleanQuery = "
            SELECT email FROM ({$campaign['query_sql']}) as targets
            WHERE email NOT IN (SELECT email FROM email_suppression_list WHERE tenant_id = ?)
        ";

        $targets = $this->db->GetAll($cleanQuery, [$tenantId]);

        $sentCount = 0;
        foreach ($targets as $t) {
             // FIRE EVENT: Email Dispatch (Individual SMTP/SES Listener listens)
             $this->fireEvent('email.individual_dispatch', [
                'campaign_id' => $campaignId,
                'email' => $t['email'],
                'template_id' => $campaign['template_id']
             ]);
             $sentCount++;
        }

        // 3. Mark as Sent
        $this->db->Execute("UPDATE email_campaigns SET status = 'sent', sent_at = NOW() WHERE id = ?", [$campaignId]);

        return [
            'total_targets' => count($targets),
            'sent_successfully' => $sentCount,
            'status' => 'completed'
        ];
    }
}
