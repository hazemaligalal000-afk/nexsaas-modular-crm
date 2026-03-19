<?php
namespace Core\Integration;

/**
 * AIInternalClient: HTTP Client for Python FastAPI internal microservice.
 * (Batch M - AI Features Wrapper)
 */
class AIInternalClient {
    private string $baseUrl;

    public function __construct() {
        $this->baseUrl = getenv('AI_ENGINE_URL') ?: 'http://ai-engine:8000';
    }

    public function post(string $endpoint, array $payload): array {
        // In production, this would use Guzzle or cURL to communicate with the Python service.
        // Mocking behavior for current environment.
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        
        if (curl_error($ch)) {
            throw new \Exception("AI Engine Communication Error: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        return json_decode($response, true) ?? [];
    }
}
