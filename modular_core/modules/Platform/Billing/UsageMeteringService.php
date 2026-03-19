<?php

namespace NexSaaS\Platform\Billing;

use Stripe\StripeClient;

/**
 * Usage Metering Service
 * Tracks and reports usage for metered billing (AI API calls, storage, etc.)
 * Requirements: Master Spec - AI Usage Metering
 */
class UsageMeteringService
{
    private $stripe;
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
        $this->stripe = new StripeClient(getenv('STRIPE_SECRET_KEY'));
    }
    
    /**
     * Record AI API usage
     */
    public function recordAIUsage(int $tenantId, string $service, int $tokens): void
    {
        // Store usage in database
        $this->db->insert('usage_records', [
            'tenant_id' => $tenantId,
            'service' => $service,
            'metric' => 'ai_tokens',
            'quantity' => $tokens,
            'recorded_at' => date('Y-m-d H:i:s')
        ]);
        
        // Get subscription item for metered billing
        $subscriptionItem = $this->getMeteredSubscriptionItem($tenantId, 'ai_usage');
        
        if ($subscriptionItem) {
            // Report usage to Stripe
            $this->stripe->subscriptionItems->createUsageRecord(
                $subscriptionItem,
                [
                    'quantity' => $tokens,
                    'timestamp' => time(),
                    'action' => 'increment'
                ]
            );
        }
    }
    
    /**
     * Record storage usage
     */
    public function recordStorageUsage(int $tenantId, int $bytes): void
    {
        // Convert bytes to GB
        $gb = $bytes / (1024 * 1024 * 1024);
        
        // Store usage
        $this->db->insert('usage_records', [
            'tenant_id' => $tenantId,
            'service' => 'storage',
            'metric' => 'storage_gb',
            'quantity' => $gb,
            'recorded_at' => date('Y-m-d H:i:s')
        ]);
        
        // Get subscription item
        $subscriptionItem = $this->getMeteredSubscriptionItem($tenantId, 'storage');
        
        if ($subscriptionItem) {
            // Report to Stripe (storage is typically reported as total, not increment)
            $this->stripe->subscriptionItems->createUsageRecord(
                $subscriptionItem,
                [
                    'quantity' => ceil($gb),
                    'timestamp' => time(),
                    'action' => 'set'
                ]
            );
        }
    }
    
    /**
     * Record API call usage
     */
    public function recordAPICall(int $tenantId, string $endpoint): void
    {
        // Store usage
        $this->db->insert('usage_records', [
            'tenant_id' => $tenantId,
            'service' => 'api',
            'metric' => 'api_calls',
            'quantity' => 1,
            'metadata' => json_encode(['endpoint' => $endpoint]),
            'recorded_at' => date('Y-m-d H:i:s')
        ]);
        
        // Get subscription item
        $subscriptionItem = $this->getMeteredSubscriptionItem($tenantId, 'api_calls');
        
        if ($subscriptionItem) {
            // Report to Stripe
            $this->stripe->subscriptionItems->createUsageRecord(
                $subscriptionItem,
                [
                    'quantity' => 1,
                    'timestamp' => time(),
                    'action' => 'increment'
                ]
            );
        }
    }
    
    /**
     * Get usage summary for tenant
     */
    public function getUsageSummary(int $tenantId, string $period = 'current_month'): array
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $query = "
            SELECT 
                service,
                metric,
                SUM(quantity) as total_quantity,
                COUNT(*) as record_count,
                MIN(recorded_at) as first_recorded,
                MAX(recorded_at) as last_recorded
            FROM usage_records
            WHERE tenant_id = ?
            AND recorded_at >= ?
            GROUP BY service, metric
        ";
        
        $results = $this->db->query($query, [$tenantId, $startDate]);
        
        return [
            'tenant_id' => $tenantId,
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => date('Y-m-d H:i:s'),
            'usage' => $results
        ];
    }
    
    /**
     * Get AI usage breakdown
     */
    public function getAIUsageBreakdown(int $tenantId, string $period = 'current_month'): array
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $query = "
            SELECT 
                service,
                SUM(quantity) as total_tokens,
                COUNT(*) as api_calls,
                AVG(quantity) as avg_tokens_per_call
            FROM usage_records
            WHERE tenant_id = ?
            AND recorded_at >= ?
            AND metric = 'ai_tokens'
            GROUP BY service
            ORDER BY total_tokens DESC
        ";
        
        $results = $this->db->query($query, [$tenantId, $startDate]);
        
        // Calculate costs (example: $0.01 per 1000 tokens)
        $totalTokens = 0;
        foreach ($results as &$row) {
            $row['estimated_cost'] = ($row['total_tokens'] / 1000) * 0.01;
            $totalTokens += $row['total_tokens'];
        }
        
        return [
            'tenant_id' => $tenantId,
            'period' => $period,
            'total_tokens' => $totalTokens,
            'total_cost' => ($totalTokens / 1000) * 0.01,
            'breakdown' => $results
        ];
    }
    
    /**
     * Check if tenant is approaching usage limits
     */
    public function checkUsageLimits(int $tenantId): array
    {
        $plan = $this->getTenantPlan($tenantId);
        $usage = $this->getUsageSummary($tenantId);
        
        $warnings = [];
        
        foreach ($usage['usage'] as $metric) {
            $limit = $plan['limits'][$metric['metric']] ?? null;
            
            if ($limit) {
                $percentage = ($metric['total_quantity'] / $limit) * 100;
                
                if ($percentage >= 90) {
                    $warnings[] = [
                        'metric' => $metric['metric'],
                        'usage' => $metric['total_quantity'],
                        'limit' => $limit,
                        'percentage' => $percentage,
                        'severity' => 'critical'
                    ];
                } elseif ($percentage >= 75) {
                    $warnings[] = [
                        'metric' => $metric['metric'],
                        'usage' => $metric['total_quantity'],
                        'limit' => $limit,
                        'percentage' => $percentage,
                        'severity' => 'warning'
                    ];
                }
            }
        }
        
        return [
            'tenant_id' => $tenantId,
            'has_warnings' => !empty($warnings),
            'warnings' => $warnings
        ];
    }
    
    /**
     * Get metered subscription item for tenant
     */
    private function getMeteredSubscriptionItem(int $tenantId, string $metricType): ?string
    {
        $query = "
            SELECT stripe_subscription_item_id
            FROM subscription_items
            WHERE tenant_id = ?
            AND metric_type = ?
            AND status = 'active'
        ";
        
        $result = $this->db->queryOne($query, [$tenantId, $metricType]);
        
        return $result['stripe_subscription_item_id'] ?? null;
    }
    
    /**
     * Get tenant plan details
     */
    private function getTenantPlan(int $tenantId): array
    {
        $query = "
            SELECT plan_id, plan_limits
            FROM tenant_subscriptions
            WHERE tenant_id = ?
        ";
        
        $result = $this->db->queryOne($query, [$tenantId]);
        
        return [
            'plan_id' => $result['plan_id'] ?? null,
            'limits' => json_decode($result['plan_limits'] ?? '{}', true)
        ];
    }
    
    /**
     * Get period start date
     */
    private function getPeriodStartDate(string $period): string
    {
        switch ($period) {
            case 'current_month':
                return date('Y-m-01 00:00:00');
            case 'last_month':
                return date('Y-m-01 00:00:00', strtotime('first day of last month'));
            case 'current_year':
                return date('Y-01-01 00:00:00');
            case 'last_7_days':
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'last_30_days':
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
            default:
                return date('Y-m-01 00:00:00');
        }
    }
    
    /**
     * Export usage data for billing
     */
    public function exportUsageForBilling(int $tenantId, string $startDate, string $endDate): array
    {
        $query = "
            SELECT *
            FROM usage_records
            WHERE tenant_id = ?
            AND recorded_at BETWEEN ? AND ?
            ORDER BY recorded_at ASC
        ";
        
        $records = $this->db->query($query, [$tenantId, $startDate, $endDate]);
        
        return [
            'tenant_id' => $tenantId,
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'total_records' => count($records),
            'records' => $records
        ];
    }
}
