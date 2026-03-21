<?php
/**
 * utils/SentryHelper.php
 * 
 * Central Error Tracking & Performance Management (Requirement 9.9)
 */

namespace NexSaaS\Platform\Obs;

use Sentry\ClientBuilder;
use Sentry\State\Hub;
use Sentry\State\Scope;

class SentryHelper
{
    private static $hub;

    public static function init()
    {
        $dsn = getenv('SENTRY_DSN');
        if (!$dsn) return;

        $options = [
            'dsn' => $dsn,
            'environment' => getenv('APP_ENV') ?: 'production',
            'release' => getenv('APP_RELEASE') ?: '1.0.0',
            'traces_sample_rate' => 1.0,
            'profiles_sample_rate' => 1.0,
        ];

        \Sentry\init($options);
    }

    /**
     * Capture exception with tenant context
     */
    public static function capture(\Throwable $exception, int $tenantId = null)
    {
        \Sentry\configureScope(function (Scope $scope) use ($tenantId): void {
            if ($tenantId) {
                $scope->setTag('tenant_id', (string)$tenantId);
            }
            $scope->setTag('server_name', gethostname());
        });

        \Sentry\captureException($exception);
    }
}
