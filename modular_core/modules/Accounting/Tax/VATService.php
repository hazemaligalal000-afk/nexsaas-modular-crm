<?php
namespace Modules\Accounting\Tax;

use Core\BaseService;
use Core\Database;

/**
 * VATService: Value Added Tax Tracking and Reporting (Batch K - Task 39.2)
 */
class VATService extends BaseService {

    /**
     * VAT tagging on invoices/bills (Req 55.4)
     */
    public function recordVATTxn(string $docType, int $docId, string $finPeriod, float $baseAmount, float $vatRate, string $vatType) {
        $db = Database::getInstance();
        $vatAmount = round($baseAmount * ($vatRate / 100), 2);
        
        $sql = "INSERT INTO vat_ledger (tenant_id, company_code, document_type, document_id, fin_period, base_amount, vat_rate, vat_amount, vat_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
        $db->query($sql, [$this->tenantId, $this->companyCode, $docType, $docId, $finPeriod, $baseAmount, $vatRate, $vatAmount, $vatType]);
        
        return $vatAmount;
    }
    
    /**
     * Egyptian VAT report per Fin_Period (Form 10) (Req 55.4)
     */
    public function getVATReport(string $finPeriod) {
        $db = Database::getInstance();
        
        $sql = "
            SELECT vat_type, 
                   SUM(base_amount) as total_base,
                   SUM(vat_amount) as total_vat, 
                   AVG(vat_rate) as average_rate
            FROM vat_ledger
            WHERE tenant_id = ? AND company_code = ? AND fin_period = ?
            GROUP BY vat_type
        ";
        
        $totals = $db->query($sql, [$this->tenantId, $this->companyCode, $finPeriod]);
        
        $outputVat = 0.00; // Sales (AR)
        $inputVat = 0.00;  // Purchases (AP)
        
        foreach ($totals as $row) {
            if ($row['vat_type'] === 'output') $outputVat += $row['total_vat'];
            if ($row['vat_type'] === 'input') $inputVat += $row['total_vat'];
        }
        
        return [
            'fin_period' => $finPeriod,
            'output_vat_collected' => $outputVat,
            'input_vat_deductible' => $inputVat,
            'net_vat_payable' => round($outputVat - $inputVat, 2),
            'details' => $totals // To populate Form 10 subsets
        ];
    }
}
