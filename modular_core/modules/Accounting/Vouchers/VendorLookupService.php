<?php
/**
 * Accounting/Vouchers/VendorLookupService.php
 *
 * Vendor Lookup Service for typeahead functionality
 * Task 30.3: Vendor lookup typeahead
 *
 * Requirements: 46.9
 */

declare(strict_types=1);

namespace Modules\Accounting\Vouchers;

use Core\BaseModel;

class VendorLookupService
{
    private BaseModel $model;
    private string $tenantId;
    private string $companyCode;
    private $db;

    public function __construct(BaseModel $model, string $tenantId, string $companyCode)
    {
        $this->model = $model;
        $this->tenantId = $tenantId;
        $this->companyCode = $companyCode;
        $this->db = $model->getDb();
    }

    /**
     * Search vendors for typeahead
     * Requirement 46.9
     *
     * @param string $query Search query
     * @param string|null $companyCode Optional company code filter
     * @param int $limit Maximum results
     * @return array
     */
    public function searchVendors(string $query, ?string $companyCode = null, int $limit = 10): array
    {
        $companyCode = $companyCode ?? $this->companyCode;
        
        // Search by vendor code or vendor name
        $sql = "
            SELECT DISTINCT
                vendor_code,
                vendor_name,
                COUNT(*) as usage_count
            FROM journal_entry_lines
            WHERE tenant_id = ?
              AND company_code = ?
              AND (
                  vendor_code ILIKE ? OR
                  vendor_name ILIKE ?
              )
              AND vendor_code IS NOT NULL
              AND deleted_at IS NULL
            GROUP BY vendor_code, vendor_name
            ORDER BY usage_count DESC, vendor_name ASC
            LIMIT ?
        ";

        $searchPattern = '%' . $query . '%';
        $result = $this->db->Execute($sql, [
            $this->tenantId,
            $companyCode,
            $searchPattern,
            $searchPattern,
            $limit
        ]);

        $vendors = [];
        while ($result && !$result->EOF) {
            $vendors[] = [
                'vendor_code' => $result->fields['vendor_code'],
                'vendor_name' => $result->fields['vendor_name'],
                'usage_count' => (int)$result->fields['usage_count'],
            ];
            $result->MoveNext();
        }

        return $vendors;
    }

    /**
     * Get vendor by code
     *
     * @param string $vendorCode Vendor code
     * @param string|null $companyCode Optional company code filter
     * @return array|null
     */
    public function getVendorByCode(string $vendorCode, ?string $companyCode = null): ?array
    {
        $companyCode = $companyCode ?? $this->companyCode;
        
        $sql = "
            SELECT 
                vendor_code,
                vendor_name,
                COUNT(*) as usage_count,
                MAX(voucher_date) as last_used
            FROM journal_entry_lines jel
            JOIN journal_entry_headers jeh ON jel.je_header_id = jeh.id
            WHERE jel.tenant_id = ?
              AND jel.company_code = ?
              AND jel.vendor_code = ?
              AND jel.deleted_at IS NULL
            GROUP BY vendor_code, vendor_name
        ";

        $result = $this->db->Execute($sql, [$this->tenantId, $companyCode, $vendorCode]);

        if (!$result || $result->EOF) {
            return null;
        }

        return [
            'vendor_code' => $result->fields['vendor_code'],
            'vendor_name' => $result->fields['vendor_name'],
            'usage_count' => (int)$result->fields['usage_count'],
            'last_used' => $result->fields['last_used'],
        ];
    }

    /**
     * Get recently used vendors
     *
     * @param string|null $companyCode Optional company code filter
     * @param int $limit Maximum results
     * @return array
     */
    public function getRecentVendors(?string $companyCode = null, int $limit = 20): array
    {
        $companyCode = $companyCode ?? $this->companyCode;
        
        $sql = "
            SELECT DISTINCT
                jel.vendor_code,
                jel.vendor_name,
                MAX(jeh.voucher_date) as last_used,
                COUNT(*) as usage_count
            FROM journal_entry_lines jel
            JOIN journal_entry_headers jeh ON jel.je_header_id = jeh.id
            WHERE jel.tenant_id = ?
              AND jel.company_code = ?
              AND jel.vendor_code IS NOT NULL
              AND jel.deleted_at IS NULL
            GROUP BY jel.vendor_code, jel.vendor_name
            ORDER BY last_used DESC
            LIMIT ?
        ";

        $result = $this->db->Execute($sql, [$this->tenantId, $companyCode, $limit]);

        $vendors = [];
        while ($result && !$result->EOF) {
            $vendors[] = [
                'vendor_code' => $result->fields['vendor_code'],
                'vendor_name' => $result->fields['vendor_name'],
                'last_used' => $result->fields['last_used'],
                'usage_count' => (int)$result->fields['usage_count'],
            ];
            $result->MoveNext();
        }

        return $vendors;
    }
}
