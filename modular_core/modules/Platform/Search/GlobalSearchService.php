<?php
namespace Modules\Platform\Search;

use Core\BaseService;
use Core\Database;

/**
 * GlobalSearchService: Full-text and PGVector semantic search
 * Phase 5 - Task 45.1
 */
class GlobalSearchService extends BaseService {
    public function search(string $query) {
        $db = Database::getInstance();
        
        // 1. Semantic Search (Call AI Engine Internal API)
        $aiEndpoint = "http://ai-engine:8000/search/semantic";
        $semanticResults = $this->callAiSearch($aiEndpoint, $query, $this->tenantId);
        
        // 2. Keyword Search (PostgreSQL Full-Text Search)
        // This is a simplified example combining leads and deals
        $sql = "
            SELECT 'lead' as type, id, first_name, last_name, email FROM leads 
            WHERE tenant_id = :tenant_id AND to_tsvector('english', first_name || ' ' || last_name || ' ' || email) @@ plainto_tsquery('english', :query)
            UNION
            SELECT 'deal' as type, id, deal_name as first_name, '' as last_name, '' as email FROM deals
            WHERE tenant_id = :tenant_id AND to_tsvector('english', deal_name) @@ plainto_tsquery('english', :query)
        ";
        
        $keywordResults = $db->query($sql, ['tenant_id' => $this->tenantId, 'query' => $query]);
        
        // 3. Deduplicate and Group Results
        // Simulated grouping for semantic and keyword
        return [
            'semantic' => $semanticResults,
            'keyword' => $keywordResults
        ];
    }
    
    private function callAiSearch(string $url, string $query, string $tenantId) {
        // Fallback or actual HTTP call in a real app
        // Simulated response for this service stub
        return [['id' => 1, 'score' => 0.95, 'text' => "Matched contextual chunk"]];
    }
}
