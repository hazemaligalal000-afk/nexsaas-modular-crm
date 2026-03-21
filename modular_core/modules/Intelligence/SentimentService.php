<?php
/**
 * Intelligence/SentimentService.php
 * 
 * CORE → ADVANCED: Dynamic Customer Sentiment Analysis & Mood Tracker
 */

declare(strict_types=1);

namespace Modules\Intelligence;

use Core\BaseService;

class SentimentService extends BaseService
{
    /**
     * Analyze text sentiment (Positive/Negative/Neutral)
     * Rule: Keyword match for Arabic (shukran, mashakir, etc.) and English
     */
    public function analyzeSentiment(string $text): array
    {
        $keywords = [
            'positive' => ['shukran', 'excellent', 'great', 'tamam', 'helpful', 'thanks', 'cool'],
            'negative' => ['mushkila', 'problem', 'bad', 'slow', 'fail', 'error', 'late', 'hate'],
        ];

        $score = 0;
        $matched = [];

        foreach ($keywords['positive'] as $k) {
             if (stripos($text, $k) !== false) {
                 $score += 1;
                 $matched[] = $k;
             }
        }

        foreach ($keywords['negative'] as $k) {
             if (stripos($text, $k) !== false) {
                 $score -= 1;
                 $matched[] = $k;
             }
        }

        $sentiment = $score > 0 ? 'Positive' : ($score < 0 ? 'Negative' : 'Neutral');

        return [
            'sentiment' => $sentiment,
            'score' => $score,
            'matched_keywords' => $matched,
            'confidence' => abs($score) > 2 ? 'High' : 'Medium'
        ];
    }
}
