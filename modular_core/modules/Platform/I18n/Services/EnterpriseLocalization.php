<?php
/**
 * ModularCore/Modules/Platform/I18n/Services/EnterpriseLocalization.php
 * Professional Multi-language & Multi-region Support (Phase 3: Advanced Scaling)
 * Fulfills the "Unicorn Scaling - Global" requirement for Egypt/GCC/Global markets.
 */

namespace ModularCore\Modules\Platform\I18n\Services;

class EnterpriseLocalization {
    
    /**
     * Resolve the active locale based on user preference or geo-location
     */
    public function resolveLocale($userProfile = null) {
        if ($userProfile && isset($userProfile['locale'])) return $userProfile['locale'];
        
        // Default to AR for GCC/MENA regions if no profile exists
        $browserLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
        if (str_contains($browserLang, 'ar')) return 'ar_SA';
        
        return 'en_US';
    }

    /**
     * Map complex currency formats for Global Markets (EGP, SAR, USD)
     */
    public function formatCurrency(float $amount, string $currencyCode) {
        $formatter = new \NumberFormatter($this->resolveLocale() . "@currency=" . $currencyCode, \NumberFormatter::CURRENCY);
        return $formatter->format($amount);
    }
}
