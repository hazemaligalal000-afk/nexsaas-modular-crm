<?php
/**
 * CustomerPortal/PortalService.php
 * 
 * CORE → ADVANCED: Self-Service Customer Experience
 */

declare(strict_types=1);

namespace Modules\CustomerPortal;

use Core\BaseService;

class PortalService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get a customer's 360-view for their self-service dashboard
     * Used by: Individual clients to see their history
     */
    public function getCustomerPerspective(int $partnerId): array
    {
        // 1. Fetch active tickets from Support module
        $tickets = $this->db->GetAll(
            "SELECT id, subject, status, priority FROM support_tickets WHERE partner_id = ? AND status != 'closed'",
            [$partnerId]
        );

        // 2. Fetch recent invoices from Invoicing module
        $invoices = $this->db->GetAll(
            "SELECT invoice_no, amount, status, created_at FROM invoices WHERE partner_id = ? ORDER BY created_at DESC LIMIT 5",
            [$partnerId]
        );

        // 3. Fetch active subscriptions/leads from Sales/SaaS modules
        $subscriptions = $this->db->GetAll(
            "SELECT plan_id, status FROM billing_subscriptions WHERE partner_id = ? AND status = 'active'",
            [$partnerId]
        );

        return [
            'overview' => [
                'active_tickets' => count($tickets),
                'unpaid_invoices' => count(array_filter($invoices, fn($i) => $i['status'] === 'unpaid')),
                'status' => count($subscriptions) > 0 ? 'Active Member' : 'Prospect'
            ],
            'recent_history' => [
                'tickets' => $tickets,
                'invoices' => $invoices
            ]
        ];
    }
}
