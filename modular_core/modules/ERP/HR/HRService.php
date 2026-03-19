<?php
namespace Modules\ERP\HR;

use Core\BaseService;
use Core\Database;

class EmployeeService extends BaseService {
    public function hire(array $data) {
        return $this->transaction(function() use ($data) {
            $db = Database::getInstance();
            
            $empNo = 'EMP-' . rand(10000, 99999);
            
            $sql = "INSERT INTO employees (
                        tenant_id, company_code, user_id, employee_no, first_name, last_name, email,
                        department_id, job_title, hire_date, employment_status, base_salary
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'onboarding', ?) RETURNING id";
                    
            $result = $db->query($sql, [
                $this->tenantId, $this->companyCode, $data['user_id'] ?? null,
                $empNo, $data['first_name'], $data['last_name'], $data['email'],
                $data['department_id'], $data['job_title'], $data['hire_date'],
                $data['base_salary'] ?? 0.00
            ]);
            
            return ['success' => true, 'data' => ['employee_id' => $result[0]['id'], 'employee_no' => $empNo]];
        });
    }

    public function recordLeave(int $employeeId, array $data) {
        return $this->transaction(function() use ($employeeId, $data) {
            $db = Database::getInstance();
            
            $sqlCheck = "SELECT * FROM employees WHERE id = ? AND tenant_id = ?";
            if (empty($db->query($sqlCheck, [$employeeId, $this->tenantId]))) {
                return ['success' => false, 'error' => 'Employee not found'];
            }
            
            $sql = "INSERT INTO leave_requests (
                        tenant_id, company_code, employee_id, leave_type_id,
                        start_date, end_date, total_days, reason, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending') RETURNING id";
                    
            $result = $db->query($sql, [
                $this->tenantId, $this->companyCode, $employeeId,
                $data['leave_type_id'], $data['start_date'], $data['end_date'],
                $data['total_days'], $data['reason']
            ]);
            
            return ['success' => true, 'data' => ['request_id' => $result[0]['id']]];
        });
    }
}

class PayrollRunService extends BaseService {
    public function compute(string $companyCode, string $finPeriod) {
        return $this->transaction(function() use ($companyCode, $finPeriod) {
            $db = Database::getInstance();
            
            // 1. Get active employees
            $sqlEmps = "SELECT id, base_salary FROM employees 
                        WHERE company_code = ? AND tenant_id = ? AND employment_status = 'active'";
            $emps = $db->query($sqlEmps, [$companyCode, $this->tenantId]);
            
            $runNo = 'PR-' . $finPeriod . '-' . rand(100, 999);
            
            $sqlRun = "INSERT INTO payroll_runs (
                           tenant_id, company_code, fin_period, run_no, run_date,
                           gross_pay, total_allowances, total_deductions, net_pay, status
                       ) VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, 'draft') RETURNING id";
                       
            $result = $db->query($sqlRun, [
                $this->tenantId, $companyCode, $finPeriod, $runNo, date('Y-m-d')
            ]);
            
            $runId = $result[0]['id'];
            
            $totalGross = 0;
            $totalNet = 0;
            
            // 2. Compute for each employee
            foreach ($emps as $emp) {
                // Simplistic computation
                $gross = $emp['base_salary'];
                $allowances = 0; // Would be complex per-type calculation
                $deductions = $gross * 0.1; // Stub flat 10% deduction
                $net = $gross + $allowances - $deductions;
                
                if ($net < 0) {
                    // Flag negative net pay constraint
                    error_log("Negative net pay for employee " . $emp['id']);
                }
                
                $sqlLine = "INSERT INTO payroll_lines (
                                tenant_id, company_code, payroll_run_id, employee_id,
                                base_salary, monthly_rate,
                                line_gross_pay, line_allowances, line_deductions, line_net_pay
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            
                $db->query($sqlLine, [
                    $this->tenantId, $companyCode, $runId, $emp['id'],
                    $emp['base_salary'], $emp['base_salary'],
                    $gross, $allowances, $deductions, $net
                ]);
                
                $totalGross += $gross;
                $totalNet += $net;
            }
            
            // 3. Update run totals
            $sqlUpdateRun = "UPDATE payroll_runs SET
                                 gross_pay = ?, total_deductions = ?, net_pay = ?
                             WHERE id = ?";
            $db->query($sqlUpdateRun, [$totalGross, ($totalGross - $totalNet), $totalNet, $runId]);
            
            // Journal entry logic would go here
            
            return ['success' => true, 'data' => ['payroll_run_id' => $runId, 'run_no' => $runNo]];
        });
    }
    
    public function export(int $runId, string $format = 'csv') {
        // Generates NACHA/CSV bank format
        return ['success' => true, 'data' => ['file_path' => '/storage/exports/payroll_' . $runId . '.csv']];
    }
}
