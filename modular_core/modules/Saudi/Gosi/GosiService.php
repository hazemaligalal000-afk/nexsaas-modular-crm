<?php

namespace ModularCore\Modules\Saudi\Gosi;

use Exception;

/**
 * GOSI Service: General Organization for Social Insurance Integration
 * Mandatory for all Saudi-based businesses.
 */
class GosiService
{
    /**
     * Requirement: Social Insurance Certificate Verification
     */
    public function getCertificateStatus($crNumber, $gosiNumber)
    {
        // Mocking GOSI API response (e.g., via Sentry/Gosi-Business API)
        return [
            'cr_number' => $crNumber,
            'gosi_number' => $gosiNumber,
            'certificate_valid' => true,
            'expiry_date' => date('Y-m-d', strtotime('+3 months')),
            'is_violation' => false,
            'accrued_fine' => 0.00,
        ];
    }

    /**
     * Requirement: Employee Monthly Contributions Tracking
     */
    public function getEmployeeContributions($nationalityId, $gosiNumber)
    {
        // Integration for GOSI-Business Portal API
        return [
            'nationality_id' => $nationalityId,
            'gosi_number' => $gosiNumber,
            'monthly_contribution' => 1200.00, // SAR 1,200/month
            'status' => 'paid',
            'last_sync' => date('Y-m-d'),
        ];
    }

    /**
     * Requirement: Saudization Contribution
     */
    public function checkSaudizationStatus($employees)
    {
        $saudiCount = count(array_filter($employees, fn($e) => $e['nationality'] === 'Saudi'));
        $totalCount = count($employees);
        $ratio = ($totalCount > 0) ? ($saudiCount / $totalCount) : 0;

        return [
            'saudi_count' => $saudiCount,
            'total_count' => $totalCount,
            'saudization_ratio' => $ratio,
            'status' => ($ratio >= 0.20) ? 'COMPLIANT' : 'NON_COMPLIANT',
            'required_saudis_to_comply' => ceil(0.20 * $totalCount) - $saudiCount,
        ];
    }
}
