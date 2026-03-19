<?php
namespace Modules\Accounting\Payroll;

use Core\BaseService;
use Core\Database;

/**
 * OnLoanEmployeeService: Inter-company salary allocations and external loanings
 * Batch H - Task 36.4
 */
class OnLoanEmployeeService extends BaseService {

    /**
     * Track and allocate on-loan employee costs (Req 52.7)
     */
    public function allocateLoanedSalary(int $employeeId, float $salaryCost, string $loanType) {
        // loan_type: 'loanee_from_other', 'loanee_to_other', 'onloan_epsco'
        
        $debitAccount = "";
        $creditAccount = "PAYROLL CLEARING";
        
        if ($loanType === 'loanee_from_other') {
            $debitAccount = "LOANES SAL. FROM OTHER";
            // The host company pays the salary physically 
        } elseif ($loanType === 'loanee_to_other') {
            $debitAccount = "LOANES SAL. TO OTHER";
            // We pay the salary but charge it out
        } elseif ($loanType === 'onloan_epsco') {
            $debitAccount = "ONLOAN EPSCO";
            // Specific partner billing
        }
        
        // Output journal structure to post
        return [
            'status' => 'allocated',
            'base_cost' => $salaryCost,
            'entries' => [
                ['account' => $debitAccount, 'dr' => $salaryCost, 'cr' => 0],
                ['account' => $creditAccount, 'dr' => 0, 'cr' => $salaryCost],
            ]
        ];
    }
}
