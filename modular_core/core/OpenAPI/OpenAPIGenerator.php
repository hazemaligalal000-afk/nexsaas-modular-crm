<?php

namespace ModularCore\Core\OpenAPI;

use ReflectionClass;
use ModularCore\Core\OpenAPI\Attributes\ApiEndpoint;
use Exception;

/**
 * Task 1.1: OpenAPI Specification Generator (Phase 1)
 */
class OpenAPIGenerator
{
    private $specs = [
        'openapi' => '3.0.1',
        'info' => [
            'title' => 'NexSaaS CRM Multi-Tenant API',
            'version' => '1.5.0',
            'description' => 'The complete strategic API suite for NexSaaS enterprise CRM.',
            'contact' => ['email' => 'dev@nexsaas.com']
        ],
        'servers' => [['url' => 'http://localhost:8000/api/v1']],
        'components' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT'
                ]
            ]
        ],
        'paths' => []
    ];

    /**
     * Scan Directory for Annotated Controllers
     */
    public function scan(string $directory): array
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        
        foreach ($files as $file) {
            if ($file->isDir()) continue;
            
            $className = $this->getClassName($file->getRealPath());
            if (!$className || !class_exists($className)) continue;

            $reflection = new ReflectionClass($className);
            foreach ($reflection->getMethods() as $method) {
                $attributes = $method->getAttributes(ApiEndpoint::class);
                foreach ($attributes as $attribute) {
                    $this->addEndpoint($attribute->newInstance());
                }
            }
        }

        return $this->specs;
    }

    private function addEndpoint(ApiEndpoint $endpoint)
    {
        $path = $endpoint->path;
        $method = strtolower($endpoint->method);

        if (!isset($this->specs['paths'][$path])) {
            $this->specs['paths'][$path] = [];
        }

        $this->specs['paths'][$path][$method] = [
            'summary'     => $endpoint->summary,
            'description' => $endpoint->description,
            'tags'        => $endpoint->tags,
            'security'    => $endpoint->authenticated ? [['bearerAuth' => []]] : [],
            'responses'   => array_map(fn($desc) => ['description' => $desc], $endpoint->responses),
            'parameters'  => $endpoint->parameters
        ];
    }

    private function getClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
            if (preg_match('/class\s+([^{ \n]+)/', $content, $classMatch)) {
                return $nsMatch[1] . '\\' . $classMatch[1];
            }
        }
        return null;
    }

    public function toJson(): string
    {
        return json_encode($this->specs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
