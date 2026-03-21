<?php
/**
 * Invoicing/InvoiceTemplateService.php
 * 
 * CORE → ADVANCED: Custom PDF Invoice Layout & Branding
 */

declare(strict_types=1);

namespace Modules\Invoicing;

use Core\BaseService;

class InvoiceTemplateService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get active template for a tenant with custom branding options
     * Used by: Individual clients to see their history
     */
    public function getActiveTemplate(string $tenantId): array
    {
        $sql = "SELECT layout_id, show_tax_breakdown, show_due_date, footer_text 
                FROM invoice_templates 
                WHERE tenant_id = ? AND is_active = TRUE";
        
        $template = $this->db->GetRow($sql, [$tenantId]);

        if (!$template) {
            return [
                'layout_id' => 'classic',
                'show_tax_breakdown' => true,
                'show_due_date' => true,
                'footer_text' => 'Thank you for your business!'
            ];
        }

        return $template;
    }

    /**
     * Update template configuration
     */
    public function updateTemplate(string $tenantId, array $data): void
    {
        $this->db->AutoExecute('invoice_templates', $data, 'UPDATE', "tenant_id = '{$tenantId}'");
    }
}
