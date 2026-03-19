<?php

namespace Accounting\COA;

use Core\BaseService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class COAService extends BaseService
{
    private COAModel $coaModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->coaModel = new COAModel();
    }
    
    /**
     * Get account balance viewer data
     * 
     * @param string $tenantId
     * @param string $companyCode
     * @param string $accountCode
     * @param string $finPeriod
     * @return array
     */
    public function getAccountBalanceViewer(string $tenantId, string $companyCode, string $accountCode, string $finPeriod): array
    {
        $account = $this->coaModel->getByCode($tenantId, $companyCode, $accountCode);
        
        if (!$account) {
            throw new \Exception("Account not found: {$accountCode}");
        }
        
        $balance = $this->coaModel->getAccountBalance($tenantId, $companyCode, $accountCode, $finPeriod);
        
        return [
            'account' => $account,
            'balance' => $balance,
            'fin_period' => $finPeriod
        ];
    }
    
    /**
     * Export COA to Excel
     * 
     * @param string $tenantId
     * @param string $companyCode
     * @return string Path to generated file
     */
    public function exportToExcel(string $tenantId, string $companyCode): string
    {
        $accounts = $this->coaModel->getAllAccounts($tenantId, $companyCode);
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Headers
        $headers = [
            'Account Code', 'Account Name (EN)', 'Account Name (AR)', 
            'Parent Code', 'Level', 'Type', 'Subtype', 
            'Allowed Currencies', 'Allowed Companies', 
            'Is Active', 'Is Blocked', 'Allow Posting', 'Balance Type'
        ];
        
        $sheet->fromArray($headers, null, 'A1');
        
        // Data
        $row = 2;
        foreach ($accounts as $account) {
            $sheet->fromArray([
                $account['account_code'],
                $account['account_name_en'],
                $account['account_name_ar'] ?? '',
                $account['parent_code'] ?? '',
                $account['account_level'],
                $account['account_type'],
                $account['account_subtype'] ?? '',
                is_array($account['allowed_currencies']) ? implode(',', $account['allowed_currencies']) : '',
                is_array($account['allowed_companies']) ? implode(',', $account['allowed_companies']) : '',
                $account['is_active'] ? 'Yes' : 'No',
                $account['is_blocked'] ? 'Yes' : 'No',
                $account['allow_posting'] ? 'Yes' : 'No',
                $account['balance_type']
            ], null, "A{$row}");
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $filename = "COA_{$companyCode}_" . date('Ymd_His') . ".xlsx";
        $filepath = sys_get_temp_dir() . '/' . $filename;
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        return $filepath;
    }
    
    /**
     * Import COA from Excel
     * 
     * @param string $tenantId
     * @param string $companyCode
     * @param string $filepath
     * @param string $userId
     * @return array Import results
     */
    public function importFromExcel(string $tenantId, string $companyCode, string $filepath, string $userId): array
    {
        $spreadsheet = IOFactory::load($filepath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        
        // Skip header row
        array_shift($rows);
        
        $imported = 0;
        $errors = [];
        
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // +2 because we skipped header and arrays are 0-indexed
            
            if (empty($row[0])) {
                continue; // Skip empty rows
            }
            
            try {
                $accountData = [
                    'tenant_id' => $tenantId,
                    'company_code' => $companyCode,
                    'account_code' => $row[0],
                    'account_name_en' => $row[1],
                    'account_name_ar' => $row[2] ?? null,
                    'parent_code' => $row[3] ?? null,
                    'account_level' => (int)$row[4],
                    'account_type' => $row[5],
                    'account_subtype' => $row[6] ?? null,
                    'allowed_currencies' => !empty($row[7]) ? explode(',', $row[7]) : [],
                    'allowed_companies' => !empty($row[8]) ? explode(',', $row[8]) : [],
                    'is_active' => strtolower($row[9]) === 'yes',
                    'is_blocked' => strtolower($row[10]) === 'yes',
                    'allow_posting' => strtolower($row[11]) === 'yes',
                    'balance_type' => $row[12] ?? 'debit',
                    'created_by' => $userId
                ];
                
                // Check if account exists
                $existing = $this->coaModel->getByCode($tenantId, $companyCode, $row[0]);
                
                if ($existing) {
                    // Update existing
                    $this->coaModel->update($existing['id'], $accountData);
                } else {
                    // Insert new
                    $this->coaModel->create($accountData);
                }
                
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNum}: " . $e->getMessage();
            }
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors
        ];
    }
    
    /**
     * Merge accounts - transfer all journal lines from source to target and block source
     * 
     * @param string $tenantId
     * @param string $companyCode
     * @param string $sourceCode
     * @param string $targetCode
     * @param string $userId
     * @return array Merge results
     */
    public function mergeAccounts(string $tenantId, string $companyCode, string $sourceCode, string $targetCode, string $userId): array
    {
        return $this->transaction(function() use ($tenantId, $companyCode, $sourceCode, $targetCode, $userId) {
            // Validate accounts exist
            $source = $this->coaModel->getByCode($tenantId, $companyCode, $sourceCode);
            $target = $this->coaModel->getByCode($tenantId, $companyCode, $targetCode);
            
            if (!$source) {
                throw new \Exception("Source account not found: {$sourceCode}");
            }
            
            if (!$target) {
                throw new \Exception("Target account not found: {$targetCode}");
            }
            
            if ($source['is_blocked']) {
                throw new \Exception("Source account is already blocked");
            }
            
            // Transfer journal lines
            $transferredLines = $this->coaModel->transferJournalLines($tenantId, $companyCode, $sourceCode, $targetCode);
            
            // Block source account
            $this->coaModel->blockAccount($tenantId, $companyCode, $sourceCode, $userId);
            
            return [
                'source_account' => $sourceCode,
                'target_account' => $targetCode,
                'transferred_lines' => $transferredLines,
                'source_blocked' => true
            ];
        });
    }
    
    /**
     * Get account usage report
     * 
     * @param string $tenantId
     * @param string $companyCode
     * @param string $accountCode
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getAccountUsageReport(string $tenantId, string $companyCode, string $accountCode, string $startDate, string $endDate): array
    {
        $account = $this->coaModel->getByCode($tenantId, $companyCode, $accountCode);
        
        if (!$account) {
            throw new \Exception("Account not found: {$accountCode}");
        }
        
        $usage = $this->coaModel->getAccountUsage($tenantId, $companyCode, $accountCode, $startDate, $endDate);
        
        return [
            'account' => $account,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'entries' => $usage,
            'total_entries' => count($usage)
        ];
    }
    
    /**
     * Create opening balance journal entry
     * 
     * @param string $tenantId
     * @param string $companyCode
     * @param string $accountCode
     * @param string $finPeriod
     * @param float $debitAmount
     * @param float $creditAmount
     * @param string $currencyCode
     * @param float $exchangeRate
     * @param string $userId
     * @return array
     */
    public function createOpeningBalance(
        string $tenantId, 
        string $companyCode, 
        string $accountCode, 
        string $finPeriod,
        float $debitAmount,
        float $creditAmount,
        string $currencyCode,
        float $exchangeRate,
        string $userId
    ): array {
        // Validate account exists
        $account = $this->coaModel->getByCode($tenantId, $companyCode, $accountCode);
        
        if (!$account) {
            throw new \Exception("Account not found: {$accountCode}");
        }
        
        // Insert into opening balances table
        $sql = "INSERT INTO account_opening_balances 
                (tenant_id, company_code, account_code, currency_code, fin_period, 
                 opening_dr, opening_cr, opening_dr_base, opening_cr_base, exchange_rate, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (tenant_id, company_code, account_code, currency_code, fin_period, deleted_at)
                DO UPDATE SET 
                    opening_dr = EXCLUDED.opening_dr,
                    opening_cr = EXCLUDED.opening_cr,
                    opening_dr_base = EXCLUDED.opening_dr_base,
                    opening_cr_base = EXCLUDED.opening_cr_base,
                    exchange_rate = EXCLUDED.exchange_rate,
                    updated_at = NOW()
                RETURNING id";
        
        $debitBase = $debitAmount * $exchangeRate;
        $creditBase = $creditAmount * $exchangeRate;
        
        $result = $this->coaModel->db->Execute($sql, [
            $tenantId, $companyCode, $accountCode, $currencyCode, $finPeriod,
            $debitAmount, $creditAmount, $debitBase, $creditBase, $exchangeRate, $userId
        ]);
        
        if (!$result || $result->EOF) {
            throw new \Exception("Failed to create opening balance");
        }
        
        return [
            'id' => $result->fields['id'],
            'account_code' => $accountCode,
            'fin_period' => $finPeriod,
            'opening_dr' => $debitAmount,
            'opening_cr' => $creditAmount,
            'currency_code' => $currencyCode
        ];
    }
    
    /**
     * Get stale WIP accounts
     * 
     * @param string $tenantId
     * @param string $companyCode
     * @param int $days
     * @return array
     */
    public function getStaleWIPAccounts(string $tenantId, string $companyCode, int $days = 90): array
    {
        return $this->coaModel->getStaleWIPAccounts($tenantId, $companyCode, $days);
    }
}
