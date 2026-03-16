<?php
/**
 * Modules/Omnichannel/Providers/TruecallerProvider.php
 * Handles Truecaller SDK/API integration for contact enrichment.
 */

namespace Modules\Omnichannel\Providers;

class TruecallerProvider {
    private $partnerKey;

    public function __construct($partnerKey) {
        $this->partnerKey = $partnerKey;
    }

    /**
     * Look up a phone number on Truecaller to identify the caller.
     */
    public function lookup($phoneNumber) {
        // Example integration with Truecaller Business API
        $url = "https://api4.truecaller.com/v1/search?q=" . urlencode($phoneNumber);
        
        $headers = [
            "Authorization: Bearer {$this->partnerKey}",
            "Cache-Control: no-cache"
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        return $this->formatEnrichedData($data);
    }

    private function formatEnrichedData($data) {
        if (!isset($data['data'][0])) return null;

        $profile = $data['data'][0];
        return [
            'name' => ($profile['name']['first'] ?? '') . ' ' . ($profile['name']['last'] ?? ''),
            'company' => $profile['companyName'] ?? null,
            'job_title' => $profile['jobTitle'] ?? null,
            'email' => $profile['onlineIdentities']['email'] ?? null,
            'avatar' => $profile['image'] ?? null,
            'city' => $profile['addresses'][0]['city'] ?? null,
            'raw_truecaller_payload' => $data
        ];
    }
}
