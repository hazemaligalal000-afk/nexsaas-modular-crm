<?php

namespace ModularCore\Modules\Marketing\EmailDesigner;

use Exception;
use GuzzleHttp\Client;

/**
 * MJML Engine: Transposes MJML templates to responsive HTML (Marketing Automation)
 */
class MjmlEngine
{
    private $apiEndpoint = 'https://api.mjml.io/v1/render'; // Mock MJML API
    private $applicationId;
    private $secretKey;

    public function __construct()
    {
        $this->applicationId = env('MJML_APP_ID');
        $this->secretKey = env('MJML_SECRET_KEY');
    }

    /**
     * Requirement: Generate Brand-Aware Template
     */
    public function render($mjmlContent, $brandConfig)
    {
        // Wrapper for MJML-to-HTML conversion
        $fullMjml = $this->wrapWithBranding($mjmlContent, $brandConfig);
        
        // Simulating the render process (Mocking MJML API response)
        $html = str_replace(['<mj-body>', '</mj-body>', '<mj-text>', '</mj-text>'], ['<body>', '</body>', '<p>', '</p>'], $fullMjml);
        
        return [
            'html' => $html,
            'mjml' => $fullMjml,
            'brand' => $brandConfig['company_name'],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Requirement: Inject Per-Company Branding (Logo, Colors, Footer)
     */
    private function wrapWithBranding($content, $brand)
    {
        $logo = $brand['logo_url'] ?? '';
        $primaryColor = $brand['primary_color'] ?? '#3b82f6';
        $footerText = $brand['footer_text'] ?? 'Powered by NexSaaS';

        return "
        <mjml>
          <mj-head>
            <mj-attributes>
              <mj-all font-family='Arial, sans-serif' />
              <mj-button background-color='{$primaryColor}' color='white' />
            </mj-attributes>
          </mj-head>
          <mj-body background-color='#f4f4f4'>
            <mj-section background-color='white' padding-bottom='20px' padding-top='20px'>
                <mj-column width='100%'>
                    <mj-image src='{$logo}' alt='Logo' width='150px' />
                </mj-column>
            </mj-section>
            {$content}
            <mj-section background-color='#f4f4f4' padding-bottom='20px' padding-top='20px'>
                <mj-column width='100%'>
                    <mj-text align='center' color='#6b7280' font-size='12px'>
                        {$footerText}
                    </mj-text>
                </mj-column>
            </mj-section>
          </mj-body>
        </mjml>
        ";
    }
}
