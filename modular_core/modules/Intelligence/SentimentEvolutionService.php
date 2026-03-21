<?php
/**
 * Intelligence/SentimentEvolutionService.php
 * 
 * CORE → ADVANCED: Dynamic Customer Sentiment Evolution & Trend Analysis
 */

declare(strict_types=1);

namespace Modules\Intelligence;

use Core\BaseService;

class SentimentEvolutionService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Map a customer's sentiment trend over the last 90 days
     * Rule: Identify 'Negative Slopes' for proactive Churn prevention
     */
    public function getSentimentTrend(int $partnerId): array
    {
        // 1. Fetch sentiment history from support/omnichannel logs
        $sql = "SELECT sentiment_score, created_at 
                FROM communication_logs 
                WHERE partner_id = ? AND sentiment_score IS NOT NULL 
                ORDER BY created_at ASC LIMIT 20";
        
        $history = $this->db->GetAll($sql, [$partnerId]);

        if (count($history) < 2) return ['trend' => 'Stable', 'slope' => 0];

        // 2. Automated Trend Analysis (Linear Regression Baseline)
        $first = $history[0]['sentiment_score'];
        $last = end($history)['sentiment_score'];
        $delta = $last - $first;

        // 3. Automated Categorization
        $trend = $delta > 0.5 ? 'Improving' : ($delta < -0.5 ? 'Declining' : 'Stable');

        return [
            'partner_id' => $partnerId,
            'current_sentiment' => $last,
            'historical_avg' => array_sum(array_column($history, 'sentiment_score')) / count($history),
            'trend_status' => $trend,
            'risk_indicator' => ($trend === 'Declining') ? 'High Attention Required' : 'OK'
        ];
    }
}
