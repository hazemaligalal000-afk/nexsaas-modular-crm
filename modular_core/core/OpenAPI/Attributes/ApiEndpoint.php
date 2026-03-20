<?php

namespace ModularCore\Core\OpenAPI\Attributes;

use Attribute;

/**
 * Task 1.1: OpenAPI Attribute for Automated Documentation
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ApiEndpoint
{
    public function __construct(
        public string $summary,
        public string $description = '',
        public string $method = 'GET',
        public string $path = '',
        public array $responses = [200 => 'Success'],
        public array $tags = [],
        public bool $authenticated = true,
        public array $parameters = []
    ) {}
}
