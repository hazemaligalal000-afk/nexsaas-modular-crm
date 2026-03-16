<?php
/**
 * Core/Jobs/EnrichLeadJob.php
 * Background job to enrich lead data via Truecaller API.
 */

namespace Core\Jobs;

use Core\Database;
use Core\TenantEnforcer;

class EnrichLeadJob {
    
    /**
     * Executes the enrichment logic.
     */
    public static function run($tenantId, $leadId, $phone) {
        try {
            // 1. Resolve Tenant Context for this background job
            // This ensures the Database::query calls are correctly scoped.
            // In a real system, the QueueWorker would call this.
            // TenantEnforcer::setContext($tenantId);

            // 2. Query Truecaller API (Mocked for Demo)
            $enrichedData = self::queryTruecaller($phone);
            
            if ($enrichedData) {
                // 3. Update the Lead record in the database
                Database::query(
                    "UPDATE contacts SET first_name = ?, last_name = ?, truecaller_verified = 1 WHERE id = ?",
                    [
                        $enrichedData['first_name'], 
                        $enrichedData['last_name'],
                        $leadId
                    ]
                );
                
                // 4. Optionally trigger AI Scoring after enrichment
                // QueueManager::push('ai_scoring', ['lead_id' => $leadId]);
            }

        } catch (\Exception $e) {
            // Log error
        }
    }

    private static function queryTruecaller($phone) {
        // In production, use file_get_contents or Guzzle to call Truecaller API
        // return json_decode(file_get_contents("https://api4.truecaller.com/v1/search?phone={$phone}"), true);
        
        return [
            'first_name' => 'John',
            'last_name' => 'Doe (Verified)',
            'gender' => 'male',
            'carrier' => 'Vodafone'
        ];
    }
}
