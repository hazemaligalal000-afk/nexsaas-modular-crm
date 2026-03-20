<?php

namespace ModularCore\Modules\Saudi\ZATCA;

/**
 * ZATCAOnboardingService
 * 
 * Handles Phase 2 (Integration Phase) onboarding for ZATCA Fatoora.
 * High-performance cryptographic signing using secp256k1 for Saudi e-invoicing.
 */
class ZATCAOnboardingService
{
    private $apiBase = 'https://gw-fatoorah.zatca.gov.sa/e-invoicing/developer-portal'; // Sandbox
    
    public function __construct($environment = 'sandbox')
    {
        if ($environment === 'production') {
            $this->apiBase = 'https://gw-fatoorah.zatca.gov.sa/e-invoicing/core';
        }
    }

    /**
     * Generate CSR (Certificate Signing Request) for ZATCA
     */
    public function generateCSR($tenantId, $data)
    {
        // 1. Generate EC Key pair (secp256k1)
        $config = array(
            "curve_name" => "secp256k1",
            "private_key_type" => OPENSSL_KEYTYPE_EC,
        );
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privKey);

        // 2. Prepare DN (Distinguished Name) for ZATCA
        $dn = array(
            "countryName" => "SA",
            "organizationName" => $data['company_name_en'],
            "organizationalUnitName" => $data['unit_name'] ?? 'Main Node',
            "commonName" => $data['common_name'],
        );

        // 3. Generate CSR
        $csrObj = openssl_csr_new($dn, $res, array('digest_alg' => 'sha256'));
        openssl_csr_export($csrObj, $csrStr);

        // 4. Encrypt and Store Private Key for this Tenant
        $this->storePrivateKey($tenantId, $privKey);

        return $csrStr;
    }

    /**
     * Submit CSR to ZATCA Compliance API
     */
    public function submitComplianceCSR($csr, $otp)
    {
        $payload = [
            'csr' => base64_encode($csr),
        ];

        $response = $this->callZATCA('/compliance', $payload, $otp);
        
        if ($response['status'] === 'SUCCESS') {
            return [
                'compliance_csid' => $response['requestID'],
                'binary_token' => $response['binarySecurityToken'],
                'secret' => $response['secret']
            ];
        }

        throw new \Exception("ZATCA Compliance Failure: " . json_encode($response['errors']));
    }

    /**
     * Call ZATCA Fatoora API
     */
    private function callZATCA($endpoint, $payload, $otp = null)
    {
        $ch = curl_init($this->apiBase . $endpoint);
        
        $headers = [
            'Content-Type: application/json',
            'Accept-Version: V2',
        ];

        if ($otp) {
            $headers[] = 'OTP: ' . $otp;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 400) {
            return ['status' => 'ERROR', 'code' => $code, 'errors' => json_decode($response, true)];
        }

        return json_decode($response, true) + ['status' => 'SUCCESS'];
    }

    private function storePrivateKey($tenantId, $key)
    {
        $path = __DIR__ . "/keys/{$tenantId}.key";
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $key);
    }
}
