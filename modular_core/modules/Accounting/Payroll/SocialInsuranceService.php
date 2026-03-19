<?php
namespace Modules\Accounting\Payroll;

use Core\BaseService;
use Core\Database;

/**
 * SocialInsuranceService: Generates Forms and tracks Insurances (Batch H - Tasks 36.5)
 */
class SocialInsuranceService extends BaseService {

    /**
     * Compute and extract Form 2 (Req 55.3, 52.9)
     */
    public function getSocialInsuranceReport(string $finPeriod) {
        $db = Database::getInstance();
        $sql = "SELECT e.employee_number, e.first_name, e.last_name, 
                       pl.employer_social_ins, pl.employee_social_ins, 
                       (pl.employer_social_ins + pl.employee_social_ins) as total_contribution
                FROM payroll_lines pl
                JOIN employees e ON pl.employee_id = e.id
                JOIN payroll_runs r ON pl.payroll_run_id = r.id
                WHERE r.tenant_id = ? AND r.company_code = ? AND r.fin_period = ? AND pl.status = 'valid'";
                
        $contributions = $db->query($sql, [$this->tenantId, $this->companyCode, $finPeriod]);
        
        $totalEmp = 0; $totalEr = 0; $grandTotal = 0;
        
        foreach ($contributions as $c) {
            $totalEmp += $c['employee_social_ins'];
            $totalEr += $c['employer_social_ins'];
            $grandTotal += $c['total_contribution'];
        }
        
        return [
            'fin_period' => $finPeriod,
            'summary' => [
                'employer_share' => $totalEr,
                'employee_share' => $totalEmp,
                'total_payable' => $grandTotal
            ],
            'details' => $contributions // Mapped row-by-row for Form 2 export
        ];
    }
}
