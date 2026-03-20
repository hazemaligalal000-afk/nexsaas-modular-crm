<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/OpenAPI/Attributes/ApiEndpoint.php';
require_once __DIR__ . '/../core/OpenAPI/OpenAPIGenerator.php';

use ModularCore\Core\OpenAPI\OpenAPIGenerator;

/**
 * Task 2.2: CLI Command for OpenAPI Generation
 */
$generator = new OpenAPIGenerator();
$controllerPath = __DIR__ . '/../modules';

echo "Scanning modules for API annotations...\n";
$spec = $generator->scan($controllerPath);

$targetFile = __DIR__ . '/../../public/openapi.json';
file_put_contents($targetFile, $generator->toJson());

echo "✅ Success! OpenAPI Spec generated at: " . realpath($targetFile) . "\n";
echo "Found " . count($spec['paths'] ?? []) . " paths across system modules.\n";
