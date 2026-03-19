<?php
/**
 * ERP/GL/FinPeriodService.php
 *
 * Financial Period Service managing period open/close/lock operations
 * Prevents new journal entries from being posted to closed periods
 *
 * Requirements: 18.13
 */

declare(strict_types=1);

namespace Modules\ERP\GL;

use Core\BaseModel;

class FinPeriodService
{
    private BaseModel $model;

    public function __construct(BaseModel $model)
    {
        $this->model = $model;
    }

    /**
     * Close a financial period for a company
     * Prevents new journal entries from being posted to this period
     *
     * Requirement 18.13
     *
     * @param string $companyCode Two-digit company code (01-06)
     * @param string $finPeriod   Financial period in YYYYMM format
     * @param string $closedBy    User ID performing the close
     *
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function close(string $companyCode, string $finPeriod, string $closedBy): array
    {
        try {
            // Check if period exists
            $period = $this->getPeriod($companyCode, $finPeriod);
            if ($period === null) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Financial period {$finPeriod} not found for company {$companyCode}"
                ];
            }

            // Check current status
            if ($period['status'] === 'closed') {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Financial period {$finPeriod} is already closed for company {$companyCode}"
                ];
            }

            if ($period['status'] === 'locked') {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Financial period {$finPeriod} is locked for company {$companyCode}. Cannot close a locked period."
                ];
            }

            // Close the period
            $sql = "UPDATE financial_periods 
                    SET status = 'closed', 
                        closed_by = ?, 
                        closed_at = CURRENT_TIMESTAMP,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE tenant_id = ? 
                        AND company_code = ? 
                        AND period_code = ? 
                        AND deleted_at IS NULL";

            $db = $this->model->getDb();
            $result = $db->Execute($sql, [
                $closedBy,
                $this->model->getTenantId(),
                $companyCode,
                $finPeriod
            ]);

            if ($result === false) {
                throw new \RuntimeException('Failed to close period: ' . $db->ErrorMsg());
            }

            if ($db->Affected_Rows() === 0) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Failed to close period {$finPeriod} for company {$companyCode}"
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'company_code' => $companyCode,
                    'fin_period' => $finPeriod,
                    'status' => 'closed',
                    'closed_by' => $closedBy,
                    'message' => "Period {$finPeriod} closed successfully. No new journal entries can be posted to this period."
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to close financial period: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lock a financial period for a company
     * Prevents any modifications to the period (stricter than close)
     *
     * @param string $companyCode Two-digit company code (01-06)
     * @param string $finPeriod   Financial period in YYYYMM format
     * @param string $lockedBy    User ID performing the lock
     *
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function lock(string $companyCode, string $finPeriod, string $lockedBy): array
    {
        try {
            // Check if period exists
            $period = $this->getPeriod($companyCode, $finPeriod);
            if ($period === null) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Financial period {$finPeriod} not found for company {$companyCode}"
                ];
            }

            // Check current status
            if ($period['status'] === 'locked') {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Financial period {$finPeriod} is already locked for company {$companyCode}"
                ];
            }

            // Lock the period
            $sql = "UPDATE financial_periods 
                    SET status = 'locked', 
                        closed_by = ?, 
                        closed_at = CURRENT_TIMESTAMP,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE tenant_id = ? 
                        AND company_code = ? 
                        AND period_code = ? 
                        AND deleted_at IS NULL";

            $db = $this->model->getDb();
            $result = $db->Execute($sql, [
                $lockedBy,
                $this->model->getTenantId(),
                $companyCode,
                $finPeriod
            ]);

            if ($result === false) {
                throw new \RuntimeException('Failed to lock period: ' . $db->ErrorMsg());
            }

            if ($db->Affected_Rows() === 0) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Failed to lock period {$finPeriod} for company {$companyCode}"
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'company_code' => $companyCode,
                    'fin_period' => $finPeriod,
                    'status' => 'locked',
                    'locked_by' => $lockedBy,
                    'message' => "Period {$finPeriod} locked successfully. No modifications allowed."
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to lock financial period: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reopen a closed financial period
     *
     * @param string $companyCode Two-digit company code (01-06)
     * @param string $finPeriod   Financial period in YYYYMM format
     *
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function reopen(string $companyCode, string $finPeriod): array
    {
        try {
            // Check if period exists
            $period = $this->getPeriod($companyCode, $finPeriod);
            if ($period === null) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Financial period {$finPeriod} not found for company {$companyCode}"
                ];
            }

            // Check current status
            if ($period['status'] === 'open') {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Financial period {$finPeriod} is already open for company {$companyCode}"
                ];
            }

            if ($period['status'] === 'locked') {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Financial period {$finPeriod} is locked for company {$companyCode}. Cannot reopen a locked period."
                ];
            }

            // Reopen the period
            $sql = "UPDATE financial_periods 
                    SET status = 'open', 
                        closed_by = NULL, 
                        closed_at = NULL,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE tenant_id = ? 
                        AND company_code = ? 
                        AND period_code = ? 
                        AND deleted_at IS NULL";

            $db = $this->model->getDb();
            $result = $db->Execute($sql, [
                $this->model->getTenantId(),
                $companyCode,
                $finPeriod
            ]);

            if ($result === false) {
                throw new \RuntimeException('Failed to reopen period: ' . $db->ErrorMsg());
            }

            if ($db->Affected_Rows() === 0) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Failed to reopen period {$finPeriod} for company {$companyCode}"
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'company_code' => $companyCode,
                    'fin_period' => $finPeriod,
                    'status' => 'open',
                    'message' => "Period {$finPeriod} reopened successfully. Journal entries can now be posted."
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to reopen financial period: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get period details
     *
     * @param string $companyCode
     * @param string $finPeriod
     * @return array|null
     */
    private function getPeriod(string $companyCode, string $finPeriod): ?array
    {
        $sql = "SELECT * FROM financial_periods 
                WHERE tenant_id = ? 
                    AND company_code = ? 
                    AND period_code = ? 
                    AND deleted_at IS NULL";

        $db = $this->model->getDb();
        $result = $db->Execute($sql, [
            $this->model->getTenantId(),
            $companyCode,
            $finPeriod
        ]);

        if (!$result || $result->EOF) {
            return null;
        }

        return $result->fields;
    }

    /**
     * Check if a period is open for posting
     *
     * @param string $companyCode
     * @param string $finPeriod
     * @return bool
     */
    public function isOpen(string $companyCode, string $finPeriod): bool
    {
        $period = $this->getPeriod($companyCode, $finPeriod);
        return $period !== null && $period['status'] === 'open';
    }
}
