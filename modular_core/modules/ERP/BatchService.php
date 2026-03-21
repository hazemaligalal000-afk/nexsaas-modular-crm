<?php
/**
 * ERP/BatchService.php
 * 
 * CORE → ADVANCED: Batch Tracking & Expiry Date Lifecycle
 */

declare(strict_types=1);

namespace Modules\ERP;

use Core\BaseService;

class BatchService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Fetch all available batches for an item (FEFO: First Expiry First Out)
     * Rule: Prioritize batches near expiry for sales
     */
    public function getAvailableBatches(string $tenantId, string $itemCode): array
    {
        $sql = "SELECT batch_no, expiry_date, current_qty 
                FROM inventory_batches 
                WHERE tenant_id = ? AND item_code = ? AND current_qty > 0 
                  AND (expiry_date > NOW() OR expiry_date IS NULL)
                ORDER BY expiry_date ASC NULLS LAST";
        
        return $this->db->GetAll($sql, [$tenantId, $itemCode]);
    }

    /**
     * Alert on near-expiry batches across warehouses
     */
    public function getExpiringSoon(string $tenantId): array
    {
        $sql = "SELECT item_code, batch_no, expiry_date, current_qty 
                FROM inventory_batches 
                WHERE tenant_id = ? AND expiry_date <= NOW() + INTERVAL '30 days' 
                  AND current_qty > 0";
        
        return $this->db->GetAll($sql, [$tenantId]);
    }
}
