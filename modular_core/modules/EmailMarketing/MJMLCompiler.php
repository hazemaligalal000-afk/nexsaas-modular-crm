<?php

namespace ModularCore\Modules\EmailMarketing;

use GuzzleHttp\Client;
use Exception;

/**
 * MJML Compiler: Production-Grade Marketing Engine (Requirement F1)
 * Orchestrates MJML-to-HTML transposition with branded multi-tenant injection.
 */
class MJMLCompiler
{
    private $mjmlApiUrl = 'https://api.mjml.io/v1/render';
    private $appId;
    private $secretKey;

    public function __construct()
    {
        $this->appId = env('MJML_APP_ID');
        $this->secretKey = env('MJML_SECRET_KEY');
    }

    /**
     * Requirement F1: Compile JSON/XML to Branded HTML
     */
    public function compile($mjmlSource, $tenantBranding = [])
    {
        // 1. Inject Per-Tenant Visual DNA (Requirement S2)
        $processedMjml = str_replace(
            ['{{PRIMARY_COLOR}}', '{{LOGO_URL}}'],
            [$tenantBranding['primary_color'] ?? '#1d4ed8', $tenantBranding['logo_url'] ?? ''],
            $mjmlSource
        );

        $client = new Client();
        try {
            $response = $client->post($this->mjmlApiUrl, [
                'auth' => [$this->appId, $this->secretKey],
                'json' => ['mjml' => $processedMjml]
            ]);

            $result = json_decode($response->getBody(), true);
            return $result['html'] ?? '';
        } catch (Exception $e) {
            \Log::error("MJML Compilation Failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Requirement F1: Wrap Raw Content into a Branded Master Template
     */
    public function wrapInMasterTemplate($bodyContent, $tenantBranding)
    {
        $master = "<mjml>
            <mj-head>
                <mj-attributes>
                    <mj-all font-family='Outfit, Arial' />
                    <mj-text font-size='16px' color='#444444' line-height='24px' />
                    <mj-button background-color='{$tenantBranding['primary_color']}' color='#ffffff' />
                </mj-attributes>
            </mj-head>
            <mj-body background-color='#f4f4f4'>
                <mj-section background-color='#ffffff' padding='20px'>
                    <mj-column>
                        <mj-image width='150px' src='{$tenantBranding['logo_url']}' />
                    </mj-column>
                </mj-section>
                {$bodyContent}
                <mj-section padding='20px'>
                    <mj-column>
                        <mj-text align='center' font-size='11px' color='#94a3b8'>
                            You received this email because of your engagement with {$tenantBranding['brand_name']}.
                        </mj-text>
                    </mj-column>
                </mj-section>
            </mj-body>
        </mjml>";

        return $this->compile($master);
    }
}
