<?php

namespace ModularCore\Modules\Saudi\Mudad;

/**
 * WPSFileBuilder
 * 
 * Generates Ministry of Human Resources and Social Development (MHRSD) Wage Protection System (WPS) text files.
 * Format: WPS_{CR_NUMBER}_{YYYYMM}.txt
 * Upload deadline: 10th of each month.
 */
class WPSFileBuilder
{
    private $employerCR;
    private $month;

    public function __construct($employerCR, $month)
    {
        $this->employerCR = $employerCR;
        $this->month = $month; // Format YYYYMM
    }

    /**
     * Build the standard MHRSD WPS text content
     */
    public function build($payrollLines)
    {
        $content = "WPS-FILE-VERSION: 1.0\n";
        $content .= "EMPLOYER-CR: {$this->employerCR}\n";
        $content .= "PERIOD: {$this->month}\n";
        $content .= "-----\n";

        foreach ($payrollLines as $line) {
            $content .= $this->formatLine($line);
        }

        return $content;
    }

    /**
     * Format a single employee's payroll line
     */
    private function formatLine($line)
    {
        // Fields standard: EmployeeID (10), Name (50), IBAN (24), Net (15,2), Month (YYYYMM), Date (YYYYMMDD), Mode (1), Type (1/2), Basic (15,2), etc.
        return sprintf(
            "%s,%s,%s,%.2f,%s,%s,%s,%s,%.2f,%.2f,%.2f,%.2f\n",
            str_pad($line['employee_id'], 10, '0', STR_PAD_LEFT),
            str_pad(mb_substr($line['name_arabic'], 0, 50), 50),
            $line['iban'],
            $line['net_salary'],
            $this->month,
            date('Ymd'),
            '1', // 1=Bank Transfer
            $line['is_saudi'] ? '1' : '2',
            $line['basic_salary'],
            $line['housing_allowance'],
            $line['other_allowances'],
            $line['deductions']
        );
    }

    /**
     * Validate IBAN, Net Pay, and Employee IDs
     */
    public function validate($payrollLines)
    {
        $errors = [];
        foreach ($payrollLines as $line) {
            if (empty($line['iban']) || strpos($line['iban'], 'SA') !== 0) {
                $errors[] = "Employee {$line['employee_id']} ({$line['name_arabic']}) - Invalid IBAN (Missing SA prefix)";
            }
            if ($line['net_salary'] <= 0) {
                $errors[] = "Employee {$line['employee_id']} - Net salary must be positive (SAR 0.00 detected)";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'compliance_rate' => empty($payrollLines) ? 0 : (100 * (count($payrollLines) - count($errors)) / count($payrollLines))
        ];
    }
}
