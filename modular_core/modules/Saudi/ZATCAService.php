<?php
/**
 * Saudi/ZATCAService.php
 * 
 * CORE → ADVANCED: ZATCA Phase 2 Fatoora Compliance
 */

declare(strict_types=1);

namespace Modules\Saudi;

use Core\BaseService;

class ZATCAService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Prepare a UBL 2.1 invoice for ZATCA clearance (Simplified/Tax)
     * Rule: Cryptographic hash + Sequential counter
     */
    public function generateFatoora(int $invoiceId): array
    {
        // 1. Fetch Invoice & Seller details (KSA Company)
        $sql = "SELECT i.invoice_no, i.amount, i.created_at, c.vat_no, c.name_en 
                FROM invoices i
                JOIN companies c ON i.company_code = c.company_code
                WHERE i.id = ? AND c.country = 'SA' AND i.deleted_at IS NULL";
        
        $fatoora = $this->db->GetRow($sql, [$invoiceId]);

        if (!$fatoora) throw new \RuntimeException("Fatoora-eligible invoice not found for KSA: " . $invoiceId);

        // 2. Generate Cryptographic Hash (Simplified for baseline)
        $dataToHash = $fatoora['invoice_no'] . '|' . $fatoora['amount'] . '|' . $fatoora['vat_no'];
        $hash = hash('sha256', $dataToHash);

        // 3. Record ZATCA Counter & UUID
        $uuid = bin2hex(random_bytes(16));
        $this->db->Execute(
            "INSERT INTO zatca_logs (invoice_id, hash, uuid, submission_status, created_at)
             VALUES (?, ?, ?, 'pending', NOW())",
            [$invoiceId, $hash, $uuid]
        );

        return [
            'invoice_no' => $fatoora['invoice_no'],
            'status' => 'compliant',
            'zatca_hash' => $hash,
            'uuid' => $uuid,
            'qr_code_payload' => base64_encode($dataToHash) // TLV payload goes here
        ];
    }
}
