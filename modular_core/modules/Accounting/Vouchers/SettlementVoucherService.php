<?php
/**
 * Accounting/Vouchers/SettlementVoucherService.php
 *
 * Settlement Voucher Engine
 * Task 30.7: Implement Settlement_Voucher engine with Voucher_Code 999 and Section_Codes 991-996
 *
 * Requirements: 47.10, 18.7
 */

declare(strict_types=1);

namespace Modules\Accounting\Vouchers;

use Core\BaseModel;

class SettlementVoucherService
{
    private BaseModel $model;
    private VoucherService $voucherService;
    private string $tenantId;
    private string $companyCode;
    private $db;

    /**
     * Settlement voucher code and section codes mapping
     * Requirement 47.10, 18.7
     */
    private const VOUCHER_SETTLEMENT = '999';
    private const SETTLEMENT_SECTIONS = [
        '01' => '991',  // EGP settlement
        '02' => '992',  // USD settlement
        '03' => '993',  // AED settlement
        '04' => '994',  // SAR settlement
        '05' => '995',  // EUR settlement
        '06' => '996',  // GBP settlement
    ];

    public function __construct(
        BaseModel $model,
        VoucherService $voucherService,
        string $tenantId,
        string $companyCode
    ) {
        $this->model = $model;
        $this->voucherService = $voucherService;
        $this->tenantId = $tenantId;
        $this->companyCode = $companyCode;
        $this->db = $model->getDb();
    }

    /**
     * Create settlement voucher for inter-currency settlement
     * Requirement 47.10
     *
     * @param array $data Settlement data
     * @param int $userId User ID creating the settlement
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function createSettlement(array $data, int $userId): array
    {
        // Validate settlement data
        $validation = $this->validateSettlementData($data);
        if (!$validation['success']) {
            return $validation;
        }

        $currencyCode = $data['currency_code'];
        $sectionCode = self::SETTLEMENT_SECTIONS[$currencyCode];

        // Build settlement voucher
        $settlementVoucher = [
            'header' => [
                'company_code' => $data['company_code'] ?? $this->companyCode,
                'fin_period' => $data['fin_period'] ?? date('Ym'),
                'voucher_date' => $data['voucher_date'] ?? date('Y-m-d'),
                'service_date' => $data['service_date'] ?? null,
                'currency_code' => $currencyCode,
                'exchange_rate' => $data['exchange_rate'] ?? 1.0,
                'voucher_code' => self::VOUCHER_SETTLEMENT,
                'section_code' => $sectionCode,
                'description' => $data['description'] ?? 'Inter-currency settlement',
            ],
            'lines' => $data['lines']
        ];

        // Validate that section code is correct for settlement
        foreach ($settlementVoucher['lines'] as &$line) {
            // Ensure all lines use the settlement section code
            $line['section_code'] = $sectionCode;
        }

        // Create settlement voucher using VoucherService
        return $this->voucherService->save($settlementVoucher, $userId);
    }

    /**
     * Calculate net settlement amount for a currency
     * Requirement 47.10
     *
     * @param string $companyCode Company code
     * @param string $currencyCode Currency code (01-06)
     * @param string $finPeriod Financial period (YYYYMM)
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function calculateNetSettlement(
        string $companyCode,
        string $currencyCode,
        string $finPeriod
    ): array {
        try {
            // Get all transactions for the currency in the period
            $sql = "
                SELECT 
                    SUM(dr_value) as total_dr,
                    SUM(cr_value) as total_cr,
                    SUM(dr_value_base) as total_dr_base,
                    SUM(cr_value_base) as total_cr_base
                FROM journal_entry_lines jel
                JOIN journal_entry_headers jeh ON jel.je_header_id = jeh.id
                WHERE jeh.tenant_id = ?
                  AND jeh.company_code = ?
                  AND jeh.currency_code = ?
                  AND jeh.fin_period = ?
                  AND jeh.status = 'posted'
                  AND jeh.deleted_at IS NULL
                  AND jel.deleted_at IS NULL
            ";

            $result = $this->db->Execute($sql, [
                $this->tenantId,
                $companyCode,
                $currencyCode,
                $finPeriod
            ]);

            if (!$result || $result->EOF) {
                return [
                    'success' => true,
                    'data' => [
                        'total_dr' => 0,
                        'total_cr' => 0,
                        'total_dr_base' => 0,
                        'total_cr_base' => 0,
                        'net_settlement' => 0,
                        'net_settlement_base' => 0,
                    ],
                    'error' => null
                ];
            }

            $totalDr = (float)$result->fields['total_dr'];
            $totalCr = (float)$result->fields['total_cr'];
            $totalDrBase = (float)$result->fields['total_dr_base'];
            $totalCrBase = (float)$result->fields['total_cr_base'];

            $netSettlement = $totalDr - $totalCr;
            $netSettlementBase = $totalDrBase - $totalCrBase;

            return [
                'success' => true,
                'data' => [
                    'company_code' => $companyCode,
                    'currency_code' => $currencyCode,
                    'fin_period' => $finPeriod,
                    'total_dr' => $totalDr,
                    'total_cr' => $totalCr,
                    'total_dr_base' => $totalDrBase,
                    'total_cr_base' => $totalCrBase,
                    'net_settlement' => $netSettlement,
                    'net_settlement_base' => $netSettlementBase,
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to calculate net settlement: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get all settlement vouchers for a period
     *
     * @param string $companyCode Company code
     * @param string $finPeriod Financial period (YYYYMM)
     * @return array
     */
    public function getSettlementVouchers(string $companyCode, string $finPeriod): array
    {
        $sql = "
            SELECT * FROM journal_entry_headers
            WHERE tenant_id = ?
              AND company_code = ?
              AND fin_period = ?
              AND voucher_code = ?
              AND deleted_at IS NULL
            ORDER BY voucher_date DESC, voucher_no DESC
        ";

        $result = $this->db->Execute($sql, [
            $this->tenantId,
            $companyCode,
            $finPeriod,
            self::VOUCHER_SETTLEMENT
        ]);

        $vouchers = [];
        while ($result && !$result->EOF) {
            $vouchers[] = $result->fields;
            $result->MoveNext();
        }

        return $vouchers;
    }

    /**
     * Validate settlement data
     */
    private function validateSettlementData(array $data): array
    {
        if (empty($data['currency_code'])) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Missing currency_code'
            ];
        }

        if (!isset(self::SETTLEMENT_SECTIONS[$data['currency_code']])) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Invalid currency_code for settlement'
            ];
        }

        if (empty($data['lines']) || !is_array($data['lines'])) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Missing settlement lines'
            ];
        }

        return ['success' => true, 'data' => null, 'error' => null];
    }
}
