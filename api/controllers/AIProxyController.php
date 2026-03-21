<?php
/**
 * controllers/AIProxyController.php
 * 
 * Secure bridge between PHP Core and Python AI Engine (Requirement 4.3).
 * Bridges CRM data to Claude-powered intelligence services.
 */

class AIProxyController 
{
    private $adb;
    private $ai_engine_url;

    public function __construct($adb) {
        $this->adb = $adb;
        // The URL of the FastAPI AI Engine (internal Docker network)
        $this->ai_engine_url = getenv('AI_ENGINE_URL') ?: 'http://ai_engine:8000';
    }

    /**
     * Proxy POST requests to the AI Engine.
     */
    public function store($input) {
        $uri = $_SERVER['REQUEST_URI'];
        // e.g. /api/ai/claude/email/draft -> /ai/claude/email/draft
        $target_path = str_replace('/api/ai', '', $uri);
        $url = $this->ai_engine_url . $target_path;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($input));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Tenant-ID: ' . \TenantHelper::getOrganizationId()
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        http_response_code($status);
        echo $response;
    }

    /**
     * Proxy GET requests to the AI Engine.
     */
    public function index() {
        $uri = $_SERVER['REQUEST_URI'];
        $target_path = str_replace('/api/ai', '', $uri);
        $url = $this->ai_engine_url . $target_path;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Tenant-ID: ' . \TenantHelper::getOrganizationId()
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        http_response_code($status);
        echo $response;
    }
}
