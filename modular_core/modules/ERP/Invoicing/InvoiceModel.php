<?php
/**
 * ERP/Invoicing/InvoiceModel.php
 *
 * Invoice model for database operations.
 */

declare(strict_types=1);

namespace Modules\ERP\Invoicing;

use Core\BaseModel;

class InvoiceModel extends BaseModel
{
    protected string $table = 'invoices';
    
    /**
     * Create a new invoice
     *
     * @param array $data
     * @return int Invoice ID
     */
    public function createInvoice(array $data): int
    {
        return $this->insert($data);
    }
    
    /**
     * Get invoice by ID
     *
     * @param int $id
     * @return array|null
     */
    public function getInvoice(int $id): ?array
    {
        return $this->findById($id);
    }
    
    /**
     * Update invoice
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateInvoice(int $id, array $data): bool
    {
        return $this->update($id, $data);
    }
    
    /**
     * Get all invoices with optional filters
     *
     * @param array $filters
     * @return array
     */
    public function getInvoices(array $filters = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['account_id'])) {
            $sql .= " AND account_id = ?";
            $params[] = $filters['account_id'];
        }
        
        if (!empty($filters['is_overdue'])) {
            $sql .= " AND is_overdue = ?";
            $params[] = $filters['is_overdue'];
        }
        
        $sql .= " ORDER BY invoice_date DESC, id DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = $filters['limit'];
        }
        
        return $this->scopeQuery($sql, $params);
    }
}
