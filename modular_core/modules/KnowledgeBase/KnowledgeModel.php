<?php
/**
 * KnowledgeBase/KnowledgeModel.php
 * 
 * CORE → ADVANCED: AI-Powered Knowledge Hub
 */

declare(strict_types=1);

namespace Modules\KnowledgeBase;

use Core\BaseModel;

class KnowledgeModel extends BaseModel
{
    protected string $table = 'knowledge_base_articles';

    /**
     * Search articles with semantic/keyword matching
     */
    public function searchArticles(string $query, string $tenantId): array
    {
        $sql = "SELECT id, title, slug, category, status 
                FROM knowledge_base_articles 
                WHERE tenant_id = ? AND (title ILIKE ? OR content ILIKE ?) AND status = 'published'
                AND deleted_at IS NULL";
        
        return $this->db->GetAll($sql, [$tenantId, "%$query%", "%$query%"]);
    }

    /**
     * Track article helpfulness (Advanced BI)
     */
    public function recordVote(int $articleId, bool $helpful): void
    {
        $column = $helpful ? 'upvotes' : 'downvotes';
        $this->db->Execute(
            "UPDATE knowledge_base_articles SET {$column} = {$column} + 1 WHERE id = ?",
            [$articleId]
        );
    }
}
