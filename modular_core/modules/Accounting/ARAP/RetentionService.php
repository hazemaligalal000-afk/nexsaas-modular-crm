<?php
namespace Modules\Accounting\ARAP;

use Core\BaseService;
use Core\Database;

/**
 * RetentionService: Tracks and releases contract retention amounts (Batch D - Task 32.5)
 */
class RetentionService extends BaseService {

    /**
     * Get unreleased retentions for Vendor
     */
    public function getRetentions(string $vendorCode) {
        $db = Database::getInstance();
        $sql = "SELECT id, bill_number, contract_reference, retention_amount
                FROM ap_bills 
                WHERE tenant_id = ? AND company_code = ? AND vendor_code = ? AND retention_amount > 0 AND status = 'open'";
        return $db->query($sql, [$this->tenantId, $this->companyCode, $vendorCode]);
    }
    
    /**
     * Release retention on milestone completion (Req 48.9)
     */
    public function releaseRetention(int $billId, float $releaseAmount) {
        $db = Database::getInstance();
        $sql = "UPDATE ap_bills 
                SET retention_amount = GREATEST(0, retention_amount - ?) 
                WHERE tenant_id = ? AND company_code = ? AND id = ? RETURNING retention_amount, vendor_code, bill_number";
                
        $res = $db->query($sql, [$releaseAmount, $this->tenantId, $this->companyCode, $billId]);
        
        // Post Journal Entry (Dr Retention Payable, Cr Accounts Payable) so the vendor is owed the retention amount now
        // Simulate posting...
        
        return $res[0];
    }
}
