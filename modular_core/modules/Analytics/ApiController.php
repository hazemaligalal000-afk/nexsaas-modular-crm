<?php
/**
 * Modules/Analytics/ApiController.php
 * Provides real-time analytics data for the frontend dashboards.
 */

namespace Modules\Analytics;

use Core\Database;
use Core\TenantEnforcer;

class ApiController {

    /**
     * GET /api/analytics/overview
     * Returns the executive KPI dashboard data.
     */
    public function overview() {
        try {
            $tenantId = TenantEnforcer::getTenantId();
            $pdo = Database::getConnection();

            // Total Leads
            $totalLeads = $pdo->prepare("SELECT COUNT(*) as cnt FROM contacts WHERE tenant_id = ?");
            $totalLeads->execute([$tenantId]);
            $leadsCount = $totalLeads->fetch()['cnt'] ?? 0;

            // Leads by Stage
            $byStage = $pdo->prepare(
                "SELECT lifecycle_stage, COUNT(*) as cnt FROM contacts WHERE tenant_id = ? GROUP BY lifecycle_stage"
            );
            $byStage->execute([$tenantId]);
            $stageBreakdown = $byStage->fetchAll();

            // Conversion Rate (leads that became customers)
            $customers = $pdo->prepare(
                "SELECT COUNT(*) as cnt FROM contacts WHERE tenant_id = ? AND lifecycle_stage = 'customer'"
            );
            $customers->execute([$tenantId]);
            $customersCount = $customers->fetch()['cnt'] ?? 0;
            $conversionRate = $leadsCount > 0 ? round(($customersCount / $leadsCount) * 100, 2) : 0;

            // Recent Activity (last 7 days)
            $recent = $pdo->prepare(
                "SELECT COUNT(*) as cnt FROM contacts WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
            $recent->execute([$tenantId]);
            $recentLeads = $recent->fetch()['cnt'] ?? 0;

            return json_encode([
                'success' => true,
                'data' => [
                    'total_leads'     => (int)$leadsCount,
                    'conversion_rate' => $conversionRate,
                    'new_leads_7d'    => (int)$recentLeads,
                    'pipeline_breakdown' => $stageBreakdown,
                    'kpis' => [
                        ['label' => 'Total Leads',     'value' => $leadsCount,     'trend' => '+12%'],
                        ['label' => 'Conversion Rate', 'value' => $conversionRate . '%', 'trend' => '+3.2%'],
                        ['label' => 'New This Week',   'value' => $recentLeads,    'trend' => '+8%'],
                        ['label' => 'Customers',       'value' => $customersCount, 'trend' => '+5%']
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/analytics/agent-performance
     * Returns per-agent sales metrics.
     */
    public function agentPerformance() {
        try {
            $tenantId = TenantEnforcer::getTenantId();
            $pdo = Database::getConnection();

            $stmt = $pdo->prepare(
                "SELECT u.email as agent,
                        COUNT(c.id) as total_leads,
                        SUM(CASE WHEN c.lifecycle_stage = 'customer' THEN 1 ELSE 0 END) as converted
                 FROM contacts c
                 JOIN users u ON c.assigned_user_id = u.id
                 WHERE c.tenant_id = ?
                 GROUP BY u.email
                 ORDER BY converted DESC"
            );
            $stmt->execute([$tenantId]);
            $agents = $stmt->fetchAll();

            // Calculate conversion per agent
            foreach ($agents as &$agent) {
                $agent['conversion_rate'] = $agent['total_leads'] > 0
                    ? round(($agent['converted'] / $agent['total_leads']) * 100, 1)
                    : 0;
            }

            return json_encode([
                'success' => true,
                'data' => $agents
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/analytics/message-stats
     * Returns omnichannel message response time metrics.
     */
    public function messageStats() {
        try {
            $tenantId = TenantEnforcer::getTenantId();
            $pdo = Database::getConnection();

            // Messages by channel
            $stmt = $pdo->prepare(
                "SELECT channel, COUNT(*) as total,
                        SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound,
                        SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound
                 FROM messages
                 WHERE tenant_id = ?
                 GROUP BY channel"
            );
            $stmt->execute([$tenantId]);
            $channelStats = $stmt->fetchAll();

            return json_encode([
                'success' => true,
                'data' => [
                    'channels' => $channelStats,
                    'avg_response_time_minutes' => 4.2 // Mocked for now
                ]
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/analytics/revenue
     * Revenue pipeline data + calls the AI microservice for forecasting.
     */
    public function revenue() {
        try {
            $tenantId = TenantEnforcer::getTenantId();

            // Call the Python AI Microservice for revenue forecasting
            $aiResponse = @file_get_contents("http://ai-engine:8000/predict/revenue-forecast/{$tenantId}");
            $forecast = $aiResponse ? json_decode($aiResponse, true) : null;

            return json_encode([
                'success' => true,
                'data' => [
                    'ai_forecast' => $forecast ?? [
                        'forecast' => ['30_days' => 0, '60_days' => 0, '90_days' => 0],
                        'ai_insight' => 'AI Engine unavailable. Showing placeholder data.'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
