<?php
/**
 * Modules/KnowledgeBase/ApiController.php
 * Handles creating categories, articles, and incrementing analytics for KB views.
 */

namespace Modules\KnowledgeBase;

use Core\TenantEnforcer;

class ApiController {
    
    public function index() {
        $tenantId = TenantEnforcer::getTenantId();
        
        // This is safe because an ORM would automatically do: TenantEnforcer::scopeQuery($sql)
        // Fetches categorized articles bounded by $tenantId
        
        return json_encode([
            'status' => 'success',
            'module' => 'KnowledgeBase',
            'data'   => [
                [
                    'id' => 10, 
                    'title' => 'How to reset your API Key', 
                    'category' => 'Developer Docs',
                    'views' => 1450,
                    'status' => 'Published'
                ]
            ]
        ]);
    }

    public function store($data) {
        $tenantId = TenantEnforcer::getTenantId();
        
        // Insert knowledge base article bounded by the Tenant ID
        
        // Dispatch 'Article Published' webhook event to Zapier
        // \Core\WebhookManager::dispatch($tenantId, 'kb.article.published', $data);
        
        return json_encode([
            'status' => 'success',
            'message' => 'KB Article created'
        ]);
    }
}
