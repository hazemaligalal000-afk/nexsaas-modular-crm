<?php

namespace Accounting\COA;

use Core\BaseModel;

class COAModel extends BaseModel
{
    protected string $table = 'chart_of_accounts';
    
    /**
     * Get account by code
     */
    public function getByCode(string $tenantId, string $companyCode, string $accountCode): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE tenant_id = ? AND company_code = ? AND account_code = ? AND deleted_at IS NULL";
        
        $result = $this->db->Execute($sql, [$tenantId, $companyCode, $accountCode]);
        return $result && !$result->EOF ? $result->fields : null;
    }
    
    /**
     * Get all accounts for a company with hierarchy
     */
    public function getAllAccounts(string $tenantId, string $companyCode, ?int $level = null): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE tenant_id = ? AND company_code = ? AND deleted_at IS NULL";
        $params = [$tenantId, $companyCode];
        
        if ($level !== null) {
            $sql .= " AND account_level = ?";
            $params[] = $level;
        }
        
        $sql .= " ORDER BY account_code";
        
        $result = $this->db->Execute($sql, $params);
        $accounts = [];
        
        while ($result && !$result->EOF) {
            $accounts[] = $result->fields;
            $result->MoveNext();
        }
        
        return $accounts;
    }
    
    /**
     * Get child accounts
     */
    public function getChildren(string $tenantId, string $companyCode, string $parentCode): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE tenant_id = ? AND company_code = ? AND parent_code = ? AND deleted_at IS NULL
                ORDER BY account_code";
        
        $result = $this->db->Execute($sql, [$tenantId, $companyCode, $parentCode]);
        $children = [];
        
        while ($result && !$result->EOF) {
            $children[] = $result->fields;
            $result->MoveNext();
        }
        
        return $children;
    }
    
    /**
     * Block an account
     */
    public function blockAccount(string $tenantId, string $companyCode, string $accountCode, string $userId): bool
    {
        $sql = "UPDATE {$this->table} 
                SET is_blocked = TRUE, updated_at = NOW(), created_by = ?
                WHERE tenant_id = ? AND company_code = ? AND account_code = ? AND deleted_at IS NULL";
        
        $result = $this->db->Execute($sql, [$userId, $tenantId, $companyCode, $accountCode]);
        return $result !== false;
    }
    
    /**
     * Transfer journal lines from source to target account
     */
    public function transferJournalLines(string $tenantId, string $companyCode, string $sourceCode, string $targetCode): int
    {
        $sql = "UPDATE journal_entry_lines 
                SET account_code = ?, account_desc = (SELECT account_name_en FROM {$this->table} WHERE account_code = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL LIMIT 1)
                WHERE tenant_id = ? AND company_code = ? AND account_code = ? AND deleted_at IS NULL";
        
        $result = $this->db->Execute($sql, [
            $targetCode, $targetCode, $tenantId, $companyCode,
            $tenantId, $companyCode, $sourceCode
        ]);
        
        return $result ? $this->db->Affected_Rows() : 0;
    }
    
    /**
     * Get account balance for a period
     */
    public function getAccountBalance(string $tenantId, string $companyCode, string $accountCode, string $finPeriod): array
    {
        $sql = "SELECT 
                    COALESCE(SUM(dr_value_egp), 0) as total_debit,
                    COALESCE(SUM(cr_value_egp), 0) as total_credit,
                    COALESCE(SUM(dr_value_egp) - SUM(cr_value_egp), 0) as net_balance
                FROM journal_entry_lines
                WHERE tenant_id = ? AND company_code = ? AND account_code = ? 
                  AND fin_period <= ? AND deleted_at IS NULL";
        
        $result = $this->db->Execute($sql, [$tenantId, $companyCode, $accountCode, $finPeriod]);
        return $result && !$result->EOF ? $result->fields : ['total_debit' => 0, 'total_credit' => 0, 'net_balance' => 0];
    }
    
    /**
     * Get account usage (journal lines) within date range
     */
    public function getAccountUsage(string $tenantId, string $companyCode, string $accountCode, string $startDate, string $endDate): array
    {
        $sql = "SELECT jel.*, je.voucher_no, je.voucher_date, je.status
                FROM journal_entry_lines jel
                JOIN journal_entry_headers je ON jel.journal_entry_id = je.id
                WHERE jel.tenant_id = ? AND jel.company_code = ? AND jel.account_code = ?
                  AND je.voucher_date BETWEEN ? AND ?
                  AND jel.deleted_at IS NULL AND je.deleted_at IS NULL
                ORDER BY je.voucher_date DESC, jel.line_no";
        
        $result = $this->db->Execute($sql, [$tenantId, $companyCode, $accountCode, $startDate, $endDate]);
        $usage = [];
        
        while ($result && !$result->EOF) {
            $usage[] = $result->fields;
            $result->MoveNext();
        }
        
        return $usage;
    }
    
    /**
     * Get WIP accounts with no movement in specified days
     */
    public function getStaleWIPAccounts(string $tenantId, string $companyCode, int $days = 90): array
    {
        $sql = "SELECT coa.*, 
                       MAX(jel.voucher_date) as last_movement_date,
                       NOW()::date - MAX(jel.voucher_date) as days_since_movement
                FROM chart_of_accounts coa
                LEFT JOIN journal_entry_lines jel ON coa.account_code = jel.account_code 
                    AND coa.tenant_id = jel.tenant_id 
                    AND coa.company_code = jel.company_code
                    AND jel.deleted_at IS NULL
                WHERE coa.tenant_id = ? AND coa.company_code = ?
                  AND coa.account_subtype = 'WIP'
                  AND coa.is_active = TRUE
                  AND coa.deleted_at IS NULL
                GROUP BY coa.id
                HAVING MAX(jel.voucher_date) IS NULL 
                    OR NOW()::date - MAX(jel.voucher_date) > ?
                ORDER BY days_since_movement DESC NULLS FIRST";
        
        $result = $this->db->Execute($sql, [$tenantId, $companyCode, $days]);
        $staleAccounts = [];
        
        while ($result && !$result->EOF) {
            $staleAccounts[] = $result->fields;
            $result->MoveNext();
        }
        
        return $staleAccounts;
    }
}
