<?php

namespace ModularCore\Modules\Saudi\Gosi;

/**
 * GOSI Calculator: Precision Social Insurance Deductions (Saudi Hub)
 * Compliance as of March 2026 for KSA Businesses.
 */
class GosiCalculator
{
    private $maxBaseSalary = 45000.00; // SAR 45k Cap for GOSI contributions
    
    // Percentages for Saudi Employees
    private $saudiEmpDeduction = 0.0975;  // 9.75%
    private $saudiEmplDeduction = 0.1175 + 0.0075; // 11.75% + 0.75% (Unified)
    private $sanedDeduction = 0.015;      // 1.5% Total (0.75% each)

    // Percentages for Expatriate Employees
    private $expatHazardDeduction = 0.02; // 2.0% Occupational Hazard only

    /**
     * Requirement: Calculate GOSI Base Salary (Basic + Housing)
     */
    public function getGosiBase($basic, $housing)
    {
        $base = $basic + $housing;
        return min($base, $this->maxBaseSalary);
    }

    /**
     * Requirement: Full Contribution Breakdown per Employee
     */
    public function calculateBreakdown($basic, $housing, $isSaudi = true)
    {
        $base = $this->getGosiBase($basic, $housing);
        
        if ($isSaudi) {
            $empShare = ($base * $this->saudiEmpDeduction) + ($base * 0.0075); // GOSI + SANED
            $emplShare = ($base * $this->saudiEmplDeduction); 
            $total = $empShare + $emplShare;
            
            return [
                'base_salary' => $base,
                'employee_share' => round($empShare, 2),
                'employer_share' => round($emplShare, 2),
                'total_contribution' => round($total, 2),
                'is_capped' => ($base >= $this->maxBaseSalary),
                'nationality' => 'Saudi'
            ];
        } else {
            // Expatriates only pay Occupational Hazards (shared or employer)
            $hazard = $base * $this->expatHazardDeduction;
            
            return [
                'base_salary' => $base,
                'employee_share' => 0.00,
                'employer_share' => round($hazard, 2),
                'total_contribution' => round($hazard, 2),
                'is_capped' => ($base >= $this->maxBaseSalary),
                'nationality' => 'Expatriate'
            ];
        }
    }
}
