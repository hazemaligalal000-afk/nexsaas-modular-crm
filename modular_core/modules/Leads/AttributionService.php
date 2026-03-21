<?php
/**
 * Leads/AttributionService.php
 * 
 * CORE → ADVANCED: Dynamic Marketing Attribution & UTM Tracking
 */

declare(strict_types=1);

namespace Modules\Leads;

use Core\BaseService;

class AttributionService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Record first-touch and last-touch attribution for a lead
     * Rule: Store UTM parameters (Source, Medium, Campaign)
     */
    public function recordAttribution(int $leadId, array $utmParams): void
    {
        $data = [
            'lead_id' => $leadId,
            'utm_source' => $utmParams['utm_source'] ?? 'direct',
            'utm_medium' => $utmParams['utm_medium'] ?? 'none',
            'utm_campaign' => $utmParams['utm_campaign'] ?? 'none',
            'referrer' => $utmParams['referrer'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];

        // 1. Persistent log for marketing BI
        $this->db->AutoExecute('lead_attribution', $data, 'INSERT');

        // 2. Update Lead Summary (First/Last touch attribution)
        $this->db->Execute(
            "UPDATE leads SET first_touch_source = COALESCE(first_touch_source, ?), last_touch_source = ? WHERE id = ?",
            [$data['utm_source'], $data['utm_source'], $leadId]
        );
    }

    /**
     * Get ROI report for marketing campaign
     */
    public function getCampaignROI(string $campaignName): array
    {
        $sql = "SELECT COUNT(*) as total_leads, 
                       (SELECT COUNT(*) FROM crm_deals d WHERE d.lead_id IN (SELECT id FROM leads l WHERE l.last_touch_source = ?)) as converted_deals
                FROM lead_attribution 
                WHERE utm_campaign = ?";
        
        return $this->db->GetRow($sql, [$campaignName, $campaignName]);
    }
}
