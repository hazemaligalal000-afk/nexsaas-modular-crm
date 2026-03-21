<?php
/**
 * Omnichannel/TemplateRegistry.php
 * 
 * CORE → ADVANCED: Unified Multi-Channel Content Registry
 */

declare(strict_types=1);

namespace Modules\Omnichannel;

use Core\BaseService;

class TemplateRegistry extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Fetch a template with multi-language support (AR/EN)
     * Channels: 'email', 'waba', 'sms'
     */
    public function getTemplate(string $slug, string $lang = 'ar'): ?array
    {
        $sql = "SELECT id, name, channel, body_ar, body_en, meta_config 
                FROM communication_templates 
                WHERE slug = ? AND status = 'active'";
        
        $template = $this->db->GetRow($sql, [$slug]);

        if (!$template) return null;

        return [
            'id' => $template['id'],
            'name' => $template['name'],
            'channel' => $template['channel'],
            'body' => ($lang === 'ar' ? $template['body_ar'] : $template['body_en']),
            'config' => json_decode($template['meta_config'], true)
        ];
    }

    /**
     * Map dynamically injected variables (e.g., {{partner_name}})
     */
    public function compileTemplate(string $body, array $data): string
    {
        foreach ($data as $key => $val) {
            $body = str_replace('{{' . $key . '}}', (string)$val, $body);
        }
        return $body;
    }
}
