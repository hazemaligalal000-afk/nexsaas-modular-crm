<?php
/**
 * Products/ProductModel.php
 * 
 * CORE → ADVANCED: Centralized Product & Price Catalog
 */

declare(strict_types=1);

namespace Modules\Products;

use Core\BaseModel;

class ProductModel extends BaseModel
{
    protected string $table = 'products';

    /**
     * Get item details for CRM Orders or ERP Inventory
     * Rule: Multi-currency price lists
     */
    public function getProductCatalog(string $tenantId, string $currencyCode): array
    {
        $sql = "SELECT p.id, p.sku, p.name_en, p.name_ar, p.category, 
                       pp.price, pp.currency_code 
                FROM products p
                LEFT JOIN product_prices pp 
                    ON p.id = pp.product_id 
                    AND pp.currency_code = ? 
                    AND pp.tenant_id = p.tenant_id
                WHERE p.tenant_id = ? AND p.is_active = TRUE AND p.deleted_at IS NULL";
        
        return $this->db->GetAll($sql, [$currencyCode, $tenantId]);
    }

    /**
     * Track SKU interaction (Popularity BI)
     */
    public function logInteraction(int $productId, string $action): void
    {
        $this->db->Execute(
            "INSERT INTO product_analytics (product_id, action, performed_at)
             VALUES (?, ?, NOW())",
            [$productId, $action]
        );
    }
}
