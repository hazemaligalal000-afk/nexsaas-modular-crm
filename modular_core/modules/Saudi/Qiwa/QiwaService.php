<?php

namespace ModularCore\Modules\Saudi\Qiwa;

use Exception;

/**
 * Qiwa Service: Labor Contract and Professional Certificate Hub Integration (Saudi Platforms)
 */
class QiwaService
{
    /**
     * Requirement: Work Permit (Work Card) Management
     */
    public function getWorkPermitStatus($iqamaNumber, $expiryDate)
    {
        $expiryTs = strtotime($expiryDate);
        $todayTs = time();
        $diffDays = floor(($expiryTs - $todayTs) / 86400);

        return [
            'iqama_number' => $iqamaNumber,
            'expiry_date' => $expiryDate,
            'status' => ($diffDays < 0) ? 'EXPIRED' : (($diffDays <= 30) ? 'CRITICAL' : 'VALID'),
            'days_remaining' => $diffDays,
            'can_renew' => true,
        ];
    }

    /**
     * Requirement: Professional Certification Tracking
     */
    public function getProfessionalCertification($iqamaNumber)
    {
        // Integration with Qiwa Professional Exam API (mock)
        return [
            'iqama_number' => $iqamaNumber,
            'job_title' => 'Civil Engineer',
            'is_certified' => true,
            'certified_at' => '2023-12-01',
            'next_exam_date' => '2026-12-01',
            'status' => 'QUALIFIED',
        ];
    }

    /**
     * Requirement: Labor Law Compliance (Qiwa Standard)
     */
    public function checkContractCompliance($contractId)
    {
        // Integration with Qiwa Contract API (mock)
        return [
            'contract_id' => $contractId,
            'has_mandatory_insurance' => true,
            'is_registered_in_gosi' => true,
            'has_standard_hours' => true,
            'risk_score' => 0.05,
            'status' => 'LEGAL',
        ];
    }
}
