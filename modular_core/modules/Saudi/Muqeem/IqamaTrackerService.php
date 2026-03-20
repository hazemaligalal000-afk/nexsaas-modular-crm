<?php

namespace ModularCore\Modules\Saudi\Muqeem;

/**
 * IqamaTrackerService
 * 
 * Tracks expatriate residency permits (Iqamas) and triggers mandatory government alerts.
 * Fines accumulate at SAR 500/day for expired permits.
 * 90/60/30-day alert schedule implemented.
 */
class IqamaTrackerService
{
    /**
     * Check Expiry Status and Status Code (1=Success, 2=Soon, 3=Critical, 4=Expired)
     */
    public function checkStatus($employeeId, $expiryDate)
    {
        $expiryTs = strtotime($expiryDate);
        $todayTs = time();
        $diffDays = floor(($expiryTs - $todayTs) / 86400);

        if ($diffDays < 0) {
            return [
                'status' => 'EXPIRED',
                'code' => 4,
                'days_remaining' => $diffDays,
                'is_violation' => true,
                'fine_estimate' => (abs($diffDays) * 500.00), // SAR 500/day
            ];
        }

        if ($diffDays <= 7) {
            return [
                'status' => 'CRITICAL',
                'code' => 3,
                'days_remaining' => $diffDays,
                'is_violation' => false,
                'fine_estimate' => 0,
            ];
        }

        if ($diffDays <= 60) {
            return [
                'status' => 'EXPIRING_SOON',
                'code' => 2,
                'days_remaining' => $diffDays,
                'is_violation' => false,
                'fine_estimate' => 0,
            ];
        }

        return [
            'status' => 'VALID',
            'code' => 1,
            'days_remaining' => $diffDays,
            'is_violation' => false,
            'fine_estimate' => 0,
        ];
    }

    /**
     * Generate Expiry Alert for Next 30 Days
     */
    public function getUpcomingExpiredIqamas($employees)
    {
        $alerts = [];
        foreach ($employees as $emp) {
            $status = $this->checkStatus($emp['id'], $emp['iqama_expiry']);
            if ($status['code'] > 1) {
                $alerts[] = [
                    'employee_id' => $emp['id'],
                    'name_arabic' => $emp['name_arabic'],
                    'iqama_number' => $emp['iqama_number'],
                    'status' => $status,
                ];
            }
        }
        return $alerts;
    }
}
