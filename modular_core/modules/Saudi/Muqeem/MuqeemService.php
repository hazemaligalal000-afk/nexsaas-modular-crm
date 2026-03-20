<?php

namespace ModularCore\Modules\Saudi\Muqeem;

use Exception;

/**
 * Muqeem Service: Expatriate Residency and Visa Management (Saudi Hub Integration)
 * Matches ZATCA/GOSI-level compliance for KSA Businesses.
 */
class MuqeemService
{
    private $finePerDay = 500.00; // SAR 500 fine for expired iqama

    /**
     * Requirement: Iqama Status & Fines
     */
    public function getIqamaStatus($iqamaNumber, $expiryDate)
    {
        $expiryTs = strtotime($expiryDate);
        $todayTs = time();
        $diffSeconds = $expiryTs - $todayTs;
        $diffDays = floor($diffSeconds / 86400);

        $status = 'valid';
        $fine = 0;

        if ($diffDays < 0) {
            $status = 'expired';
            $fine = abs($diffDays) * $this->finePerDay;
        } elseif ($diffDays <= 30) {
            $status = 'critical';
        } elseif ($diffDays <= 90) {
            $status = 'warning';
        }

        return [
            'iqama_number' => $iqamaNumber,
            'status' => $status,
            'days_remaining' => $diffDays,
            'accrued_fine' => $fine,
            'is_actionable' => ($status !== 'valid'),
        ];
    }

    /**
     * Requirement: Exit/Entry Visa Monitoring
     */
    public function trackVisaStatus($passportNumber, $visaNumber, $returnDate)
    {
        $returnTs = strtotime($returnDate);
        $todayTs = time();
        $diffDays = floor(($returnTs - $todayTs) / 86400);

        return [
            'visa_number' => $visaNumber,
            'passport_number' => $passportNumber,
            'return_by' => $returnDate,
            'days_overdue' => ($diffDays < 0) ? abs($diffDays) : 0,
            'status' => ($diffDays < 0) ? 'OVERDUE' : 'ABROAD',
        ];
    }

    /**
     * Sync with Gov Platform (Mock for ELM/Muqeem API)
     */
    public function syncGovData($iqamaNumber)
    {
        // Integration with ELM API (Abshir Business)
        return [
            'iqama_number' => $iqamaNumber,
            'full_name' => 'Expatriate Employee Example',
            'nationality' => 'Indian',
            'sponsor_id' => '7001234567',
            'last_sync' => date('Y-m-d H:i:s'),
        ];
    }
}
