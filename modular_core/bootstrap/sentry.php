<?php

use Sentry\State\Hub;
use Sentry\State\Scope;

/**
 * Task 17.1: Sentry PHP Backend Integration (Phase 4)
 */
return function($app) {
    if (!env('SENTRY_LARAVEL_DSN')) return;

    \Sentry\init([
        'dsn' => env('SENTRY_LARAVEL_DSN'),
        'environment' => env('APP_ENV', 'production'),
        'release' => 'nexsaas-crm@' . env('APP_VERSION', '1.5.0'),
        
        # Requirement 14.4: PII Scrubbing
        'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
             $payload = $event->getPayload();
             if (isset($payload['user']['email'])) {
                 $payload['user']['email'] = '[SCRUBBED]';
             }
             return $event;
        },
        
        # Requirement 16.1: Performance Tracking
        'traces_sample_rate' => 1.0,
    ]);

    # Set Global Context
    \Sentry\configureScope(function (Scope $scope) use ($app): void {
        $scope->setTag('platform', 'php-8.3');
        $scope->setExtra('multi_tenant_enabled', true);
    });
};
