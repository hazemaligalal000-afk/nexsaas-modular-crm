<?php
/**
 * Invoicing/InvoiceService.php
 * 
 * CORE → ADVANCED: Automated Billing & PDF Ledger (Invoicing-A)
 */

declare(strict_types=1);

namespace Modules\Invoicing;

use Core\BaseService;

class InvoiceService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Generate a recurring monthly invoice for a SaaS tenant
     * Rule: Calculated based on active users + plan base price
     */
    public function generateMonthlyInvoice(string $tenantId, string $finPeriod): array
    {
        // 1. Fetch Plan & Active User Count
        $sql = "SELECT s.plan_id, s.base_price, s.price_per_user,
                       (SELECT COUNT(*) FROM users WHERE tenant_id = ? AND is_active = TRUE) as total_users
                FROM billing_subscriptions s
                WHERE s.tenant_id = ? AND s.status = 'active'";
        
        $sub = $this->db->GetRow($sql, [$tenantId, $tenantId]);

        if (!$sub) throw new \RuntimeException("No active subscription found for tenant: " . $tenantId);

        $totalAmount = $sub['base_price'] + ($sub['price_per_user'] * $sub['total_users']);

        // 2. Insert Invoice Record
        $invNo = 'INV-' . $finPeriod . '-' . strtoupper(substr($tenantId, 0, 4));
        $data = [
            'tenant_id' => $tenantId,
            'invoice_no' => $invNo,
            'period' => $finPeriod,
            'amount' => $totalAmount,
            'status' => 'unpaid',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->db->AutoExecute('invoices', $data, 'INSERT');
        $id = (int)$this->db->Insert_ID();

        // 3. Automated Accounting Entry (Batch ERP Integration)
        // If Accounting module exists, create a Receivable Voucher (Type 1)
        if (class_exists('Modules\Accounting\JournalEntryModel')) {
            // This would call JournalEntryModel::create (...)
        }

        // 4. FIRE EVENT: Invoice Created (Triggers Email/Waba notification)
        // $this->fireEvent('billing.invoice_created', $data);

        return $data;
    }
}
