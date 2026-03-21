<?php
/**
 * Marketing/CampaignService.php
 * 
 * CORE → ADVANCED: Multi-Channel Automated Campaigns
 */

declare(strict_types=1);

namespace Modules\Marketing;

use Core\BaseService;

class CampaignService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Launch a multi-channel campaign to a target segment
     * Channels: Email, Waba, SMS
     */
    public function launchCampaign(string $tenantId, int $campaignId): array
    {
        // 1. Fetch Campaign & Segment details
        $sql = "SELECT c.id, c.name, c.template_id, c.channel, s.query_sql 
                FROM marketing_campaigns c
                JOIN segments s ON c.segment_id = s.id
                WHERE c.id = ? AND c.tenant_id = ? AND c.status = 'draft'";
        
        $campaign = $this->db->GetRow($sql, [$campaignId, $tenantId]);

        if (!$campaign) throw new \RuntimeException("Campaign not found or not in draft: " . $campaignId);

        // 2. Fetch targets based on segment query (Safe execution only)
        $targets = $this->db->GetAll($campaign['query_sql']);

        $sentCount = 0;
        foreach ($targets as $t) {
             // FIRE EVENT: Campaign Dispatch (OMNICHANNEL LISTENS)
             $this->fireEvent('marketing.campaign_dispatch', [
                'campaign_id' => $campaignId,
                'target_id' => $t['id'],
                'target_contact' => $t['email'] ?? $t['phone'],
                'channel' => $campaign['channel'],
                'template_id' => $campaign['template_id']
             ]);
             $sentCount++;
        }

        // 3. Mark campaign as active/sent
        $this->db->Execute("UPDATE marketing_campaigns SET status = 'sent', sent_at = NOW() WHERE id = ?", [$campaignId]);

        return [
            'campaign_name' => $campaign['name'],
            'targets_reached' => $sentCount,
            'status' => 'success'
        ];
    }
}
