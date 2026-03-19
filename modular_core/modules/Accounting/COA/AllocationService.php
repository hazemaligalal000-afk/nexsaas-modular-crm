<?php

namespace Accounting\COA;

use Core\BaseService;

class AllocationService extends BaseService
{
    private COAModel $coaModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->coaModel = new COAModel();
    }
    
    /**
     * Get allocation rules for an account
     * 
     * @param string $tenantId
     * @param string $companyCode
     * @param string $sourceAccountCode
     * @return array
     */
    public function getAllocationRules(string $tenantId, string $companyCode, string $sourceAccountCode): array
    {
        $sql = "SELECT * FROM allocation_account_rules
                WHERE tenant_id = ? AND company_code = ? AND source_account_code = ?
                  AND is_active = TRUE AND deleted_at IS NULL
                ORDER BY target_account_code";
        
        $result = $this->coaModel->db->Execute($sql, [$tenantId, $companyCode, $sourceAccountCode]);
        $rules = [];
        
        while ($result && !$result->EOF) {
            $rules[] = $result->fields;
            $result->MoveNext();
        }
        
        return $rules;
    }
    
    /**
     * Validate allocation rules sum to 100%
     * 
     * @param array $rules
     * @return bool
     */
    private function validateAllocationRules(array $rules): bool
    {
        $totalPercentage = array_sum(array_column($rules, 'allocation_percentage'));
        return abs($totalPercentage - 100.0) < 0.01; // Allow for floating point precision
    }
    
    /**
     * Distribute amount from allocation account to target accounts
     * 
     * @param int $journalLineId The journal entry line ID that posted to an allocation account
     * @param string $tenantId
     * @param string $companyCode
     * @param string $userId
     * @return array Distribution results
     */
    public function distribute(int $journalLineId, string $tenantId, string $companyCode, string $userId): array
    {
        return $this->transaction(function() use ($journalLineId, $tenantId, $companyCode, $userId) {
            // Get the journal line
            $sql = "SELECT jel.*, coa.account_type
                    FROM journal_entry_lines jel
                    JOIN chart_of_accounts coa ON jel.account_code = coa.account_code
                        AND jel.tenant_id = coa.tenant_id
                        AND jel.company_code = coa.company_code
                        AND coa.deleted_at IS NULL
                    WHERE jel.id = ? AND jel.tenant_id = ? AND jel.company_code = ?
                      AND jel.deleted_at IS NULL";
            
            $result = $this->coaModel->db->Execute($sql, [$journalLineId, $tenantId, $companyCode]);
            
            if (!$result || $result->EOF) {
                throw new \Exception("Journal line not found: {$journalLineId}");
            }
            
            $journalLine = $result->fields;
            
            // Verify it's an allocation account
            if ($journalLine['account_type'] !== 'Allocation') {
                throw new \Exception("Account {$journalLine['account_code']} is not an allocation account");
            }
            
            // Get allocation rules
            $rules = $this->getAllocationRules($tenantId, $companyCode, $journalLine['account_code']);
            
            if (empty($rules)) {
                throw new \Exception("No allocation rules found for account {$journalLine['account_code']}");
            }
            
            // Validate rules sum to 100%
            if (!$this->validateAllocationRules($rules)) {
                throw new \Exception("Allocation rules do not sum to 100% for account {$journalLine['account_code']}");
            }
            
            // Calculate amounts to distribute
            $drAmount = (float)$journalLine['dr_value'];
            $crAmount = (float)$journalLine['cr_value'];
            $drAmountEgp = (float)$journalLine['dr_value_egp'];
            $crAmountEgp = (float)$journalLine['cr_value_egp'];
            
            $distributionLines = [];
            
            // Create distribution lines for each target account
            foreach ($rules as $rule) {
                $percentage = (float)$rule['allocation_percentage'] / 100.0;
                
                $allocatedDr = round($drAmount * $percentage, 2);
                $allocatedCr = round($crAmount * $percentage, 2);
                $allocatedDrEgp = round($drAmountEgp * $percentage, 2);
                $allocatedCrEgp = round($crAmountEgp * $percentage, 2);
                
                // Get target account details
                $targetAccount = $this->coaModel->getByCode($tenantId, $companyCode, $rule['target_account_code']);
                
                if (!$targetAccount) {
                    throw new \Exception("Target account not found: {$rule['target_account_code']}");
                }
                
                // Insert distribution line
                $insertSql = "INSERT INTO journal_entry_lines (
                    tenant_id, company_code, journal_entry_id, area_code, area_desc,
                    fin_period, voucher_date, service_date, voucher_no, section_code,
                    voucher_sub, line_no, account_code, account_desc, cost_identifier,
                    cost_center_code, cost_center_name, vendor_code, vendor_name,
                    check_transfer_no, exchange_rate, currency_code, dr_value, cr_value,
                    dr_value_egp, cr_value_egp, line_desc, asset_no, transaction_no,
                    profit_loss_flag, customer_invoice_no, income_stmt_flag,
                    internal_invoice_no, employee_no, partner_no, vendor_word_count,
                    translator_word_count, agent_name, created_by
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, (SELECT COALESCE(MAX(line_no), 0) + 1 FROM journal_entry_lines WHERE journal_entry_id = ?), 
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?
                ) RETURNING id";
                
                $lineDesc = "Allocation from {$journalLine['account_code']} ({$rule['allocation_percentage']}%)";
                
                $insertResult = $this->coaModel->db->Execute($insertSql, [
                    $tenantId, $companyCode, $journalLine['journal_entry_id'],
                    $journalLine['area_code'], $journalLine['area_desc'],
                    $journalLine['fin_period'], $journalLine['voucher_date'], $journalLine['service_date'],
                    $journalLine['voucher_no'], $journalLine['section_code'],
                    $journalLine['voucher_sub'], $journalLine['journal_entry_id'],
                    $rule['target_account_code'], $targetAccount['account_name_en'], $journalLine['cost_identifier'],
                    $journalLine['cost_center_code'], $journalLine['cost_center_name'],
                    $journalLine['vendor_code'], $journalLine['vendor_name'],
                    $journalLine['check_transfer_no'], $journalLine['exchange_rate'], $journalLine['currency_code'],
                    $allocatedDr, $allocatedCr,
                    $allocatedDrEgp, $allocatedCrEgp,
                    $lineDesc, $journalLine['asset_no'], $journalLine['transaction_no'],
                    $journalLine['profit_loss_flag'], $journalLine['customer_invoice_no'], $journalLine['income_stmt_flag'],
                    $journalLine['internal_invoice_no'], $journalLine['employee_no'], $journalLine['partner_no'],
                    $journalLine['vendor_word_count'], $journalLine['translator_word_count'], $journalLine['agent_name'],
                    $userId
                ]);
                
                if (!$insertResult || $insertResult->EOF) {
                    throw new \Exception("Failed to create distribution line for account {$rule['target_account_code']}");
                }
                
                $distributionLines[] = [
                    'id' => $insertResult->fields['id'],
                    'target_account' => $rule['target_account_code'],
                    'percentage' => $rule['allocation_percentage'],
                    'dr_value' => $allocatedDr,
                    'cr_value' => $allocatedCr,
                    'dr_value_egp' => $allocatedDrEgp,
                    'cr_value_egp' => $allocatedCrEgp
                ];
            }
            
            return [
                'source_line_id' => $journalLineId,
                'source_account' => $journalLine['account_code'],
                'distribution_lines' => $distributionLines,
                'total_distributed' => count($distributionLines)
            ];
        });
    }
    
    /**
     * Create or update allocation rule
     * 
     * @param string $tenantId
     * @param string $companyCode
     * @param string $sourceAccountCode
     * @param string $targetAccountCode
     * @param float $percentage
     * @param string $userId
     * @return array
     */
    public function saveAllocationRule(
        string $tenantId,
        string $companyCode,
        string $sourceAccountCode,
        string $targetAccountCode,
        float $percentage,
        string $userId
    ): array {
        // Validate source account is allocation type
        $sourceAccount = $this->coaModel->getByCode($tenantId, $companyCode, $sourceAccountCode);
        
        if (!$sourceAccount) {
            throw new \Exception("Source account not found: {$sourceAccountCode}");
        }
        
        if ($sourceAccount['account_type'] !== 'Allocation') {
            throw new \Exception("Source account must be of type 'Allocation'");
        }
        
        // Validate target account exists
        $targetAccount = $this->coaModel->getByCode($tenantId, $companyCode, $targetAccountCode);
        
        if (!$targetAccount) {
            throw new \Exception("Target account not found: {$targetAccountCode}");
        }
        
        // Validate percentage
        if ($percentage < 0 || $percentage > 100) {
            throw new \Exception("Percentage must be between 0 and 100");
        }
        
        // Insert or update rule
        $sql = "INSERT INTO allocation_account_rules 
                (tenant_id, company_code, source_account_code, target_account_code, allocation_percentage, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT (tenant_id, company_code, source_account_code, target_account_code, deleted_at)
                DO UPDATE SET 
                    allocation_percentage = EXCLUDED.allocation_percentage,
                    is_active = TRUE,
                    updated_at = NOW()
                RETURNING id";
        
        $result = $this->coaModel->db->Execute($sql, [
            $tenantId, $companyCode, $sourceAccountCode, $targetAccountCode, $percentage, $userId
        ]);
        
        if (!$result || $result->EOF) {
            throw new \Exception("Failed to save allocation rule");
        }
        
        return [
            'id' => $result->fields['id'],
            'source_account' => $sourceAccountCode,
            'target_account' => $targetAccountCode,
            'percentage' => $percentage
        ];
    }
    
    /**
     * Delete allocation rule
     * 
     * @param string $tenantId
     * @param string $companyCode
     * @param int $ruleId
     * @return bool
     */
    public function deleteAllocationRule(string $tenantId, string $companyCode, int $ruleId): bool
    {
        $sql = "UPDATE allocation_account_rules 
                SET deleted_at = NOW()
                WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL";
        
        $result = $this->coaModel->db->Execute($sql, [$ruleId, $tenantId, $companyCode]);
        return $result !== false;
    }
}
