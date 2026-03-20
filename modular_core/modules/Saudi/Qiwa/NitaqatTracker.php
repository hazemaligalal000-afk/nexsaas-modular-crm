<?php

namespace ModularCore\Modules\Saudi\Qiwa;

/**
 * NitaqatTracker
 * 
 * Tracks Saudization (Nitaqat) bands and establishment compliance scores.
 * Supporting July 2025 engineering quotas (30%).
 * Bands: Platinum, Green, Yellow, Red.
 */
class NitaqatTracker
{
    /**
     * Get Current Nitaqat Band and Percentage
     */
    public function getBand($saudiPercent)
    {
        if ($saudiPercent >= 50) return ['band' => 'PLATINUM', 'color' => '#05ff91', 'access' => 'MAXIMUM'];
        if ($saudiPercent >= 30) return ['band' => 'GREEN', 'color' => '#10b981', 'access' => 'NORMAL'];
        if ($saudiPercent >= 20) return ['band' => 'YELLOW', 'color' => '#f59e0b', 'access' => 'RESTRICTED'];
        return ['band' => 'RED', 'color' => '#ef4444', 'access' => 'BLOCKED'];
    }

    /**
     * Calculate Saudization Rate for Establishment
     */
    public function calculateSaudizationRate($totalEmployees, $saudiEmployees)
    {
        if ($totalEmployees <= 0) return 0.00;
        return round(($saudiEmployees / $totalEmployees) * 100, 2);
    }
}
