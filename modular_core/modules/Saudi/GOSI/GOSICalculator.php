<?php

namespace ModularCore\Modules\Saudi\GOSI;

/**
 * GOSICalculator
 * 
 * Handles the logic for Social Insurance (GOSI) calculation in Saudi Arabia.
 * Updated 2026 rules:
 * - Saudi: 9% Employee + 9% Employer + 2% Occupational Hazard (Total 11% Employer cost).
 * - Expat: 2% Occupational Hazard (Employer only).
 * - Salary Cap: SAR 45,000.
 * - Salary Floor: SAR 1,500.
 */
class GOSICalculator
{
    private $cap = 45000.00;
    private $floor = 1500.00;

    /**
     * Calculate Monthy GOSI Contribution
     */
    public function calculate($employeeId, $salaryBasic, $salaryHousing, $isSaudi)
    {
        $base = $salaryBasic + $salaryHousing;
        
        // Apply GOSI Floor and Cap
        if ($base < $this->floor) $base = $this->floor;
        if ($base > $this->cap) $base = $this->cap;

        $result = [
            'base_salary' => $base,
            'is_saudi' => $isSaudi,
            'employee_deduction' => 0.00,
            'employer_cost' => 0.00,
            'occupational_hazard' => ($base * 0.02), // 2% for both Saudi and Non-Saudi
        ];

        if ($isSaudi) {
            // General Pension (9% Employee, 9% Employer)
            $result['employee_deduction'] = ($base * 0.09);
            $result['employer_cost'] = ($base * 0.09) + $result['occupational_hazard'];
        } else {
            // Non-Saudi only pays Occupational Hazard
            $result['employee_deduction'] = 0.00;
            $result['employer_cost'] = $result['occupational_hazard'];
        }

        return $result;
    }

    /**
     * Generate Monthly GOSI Report Data (Format per GOSI Portal)
     */
    public function generateMonthlyReportDataset($employees)
    {
        $report = [];
        foreach ($employees as $emp) {
            $report[] = $this->calculate(
                $emp['id'], 
                $emp['salary_basic'], 
                $emp['salary_housing'], 
                $emp['nationality'] === 'SA'
            );
        }
        return $report;
    }
}
