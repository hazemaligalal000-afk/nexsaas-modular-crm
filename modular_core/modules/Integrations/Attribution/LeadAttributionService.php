<?php
/**
 * Integrations/Attribution/LeadAttributionService.php
 *
 * Stores lead attribution data and queues CAPI events.
 */

declare(strict_types=1);

namespace Integrations\Attribution;

use Core\BaseService;

class LeadAttributionService extends BaseService
{
    private string $tenantId;
    private string $companyCode;
    private $queue;

    public function __construct($db, $queue, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->queue       = $queue;
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    /**
     * Store attribution data for a new lead/contact.
     * Returns the attribution ID.
     */
    public function store(array $data): int
    {
        $now = $this->now();
        $rs  = $this->db->Execute(
            'INSERT INTO lead_attributions (
                tenant_id, company_code, contact_id, platform,
                gclid, fbclid, ttclid, sccid, li_fat_id, twclid, msclkid, wbraid, gbraid,
                utm_source, utm_medium, utm_campaign, utm_content, utm_term, utm_id,
                ad_platform_id, ad_set_id, ad_id, campaign_name, ad_set_name, ad_name,
                creative_id, placement, conversion_type, touch_position,
                landing_page_url, referrer_url, ip_address, user_agent,
                device_type, browser, os, country_code, city,
                platform_lead_id, platform_form_id, platform_ad_account,
                fbc, fbp, raw_payload, created_at, updated_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id',
            [
                $this->tenantId, $this->companyCode,
                $data['contact_id']           ?? null,
                $data['platform']             ?? 'unknown',
                $data['gclid']                ?? null,
                $data['fbclid']               ?? null,
                $data['ttclid']               ?? null,
                $data['sccid']                ?? null,
                $data['li_fat_id']            ?? null,
                $data['twclid']               ?? null,
                $data['msclkid']              ?? null,
                $data['wbraid']               ?? null,
                $data['gbraid']               ?? null,
                $data['utm_source']           ?? null,
                $data['utm_medium']           ?? null,
                $data['utm_campaign']         ?? null,
                $data['utm_content']          ?? null,
                $data['utm_term']             ?? null,
                $data['utm_id']               ?? null,
                $data['ad_platform_id']       ?? null,
                $data['ad_set_id']            ?? null,
                $data['ad_id']                ?? null,
                $data['campaign_name']        ?? null,
                $data['ad_set_name']          ?? null,
                $data['ad_name']              ?? null,
                $data['creative_id']          ?? null,
                $data['placement']            ?? null,
                $data['conversion_type']      ?? 'lead',
                $data['touch_position']       ?? 'last',
                $data['landing_page_url']     ?? null,
                $data['referrer_url']         ?? null,
                $data['ip_address']           ?? null,
                $data['user_agent']           ?? null,
                $data['device_type']          ?? null,
                $data['browser']              ?? null,
                $data['os']                   ?? null,
                $data['country_code']         ?? null,
                $data['city']                 ?? null,
                $data['platform_lead_id']     ?? null,
                $data['platform_form_id']     ?? null,
                $data['platform_ad_account']  ?? null,
                $data['fbc']                  ?? null,
                $data['fbp']                  ?? null,
                isset($data['raw']) ? json_encode($data['raw']) : null,
                $now, $now,
            ]
        );

        if ($rs === false) {
            throw new \RuntimeException('LeadAttributionService::store failed: ' . $this->db->ErrorMsg());
        }

        $id = (!$rs->EOF) ? (int)$rs->fields['id'] : (int)$this->db->Insert_ID();

        // Queue CAPI event for the originating platform
        if ($this->queue && !empty($data['platform'])) {
            $this->queue->publish('attribution.capi', [
                'tenant_id'      => $this->tenantId,
                'attribution_id' => $id,
                'platform'       => $data['platform'],
                'event_name'     => 'Lead',
                'contact_id'     => $data['contact_id'] ?? null,
            ]);
        }

        return $id;
    }

    /**
     * Mark CAPI as sent for a platform.
     */
    public function markCapiSent(int $attributionId, string $platform, float $matchScore = 0.0): void
    {
        $field = "capi_sent_{$platform}";
        $this->db->Execute(
            "UPDATE lead_attributions SET {$field} = TRUE, capi_sent_at = ?, capi_match_score = ?, updated_at = ?
             WHERE id = ? AND tenant_id = ?",
            [$this->now(), $matchScore, $this->now(), $attributionId, $this->tenantId]
        );
    }

    /**
     * Get attribution stats grouped by platform/campaign.
     */
    public function stats(array $filters = []): array
    {
        $where  = ['tenant_id = ?'];
        $params = [$this->tenantId];

        if (!empty($filters['date_from'])) {
            $where[]  = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'created_at <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['platform'])) {
            $where[]  = 'platform = ?';
            $params[] = $filters['platform'];
        }

        $rs = $this->db->Execute(
            'SELECT platform, utm_campaign, utm_source, utm_medium,
                    COUNT(*) AS leads,
                    SUM(CASE WHEN capi_sent_meta THEN 1 ELSE 0 END) AS capi_meta,
                    SUM(CASE WHEN capi_sent_google THEN 1 ELSE 0 END) AS capi_google
             FROM lead_attributions WHERE ' . implode(' AND ', $where) . '
             GROUP BY platform, utm_campaign, utm_source, utm_medium
             ORDER BY leads DESC',
            $params
        );

        if ($rs === false) {
            return [];
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
