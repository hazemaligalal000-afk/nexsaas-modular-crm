<?php

namespace ModularCore\Core\MultiTenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Requirement 7: Tenant Query Interceptor
 * Automatically injects tenant_id into all database queries
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant()
    {
        static::creating(function (Model $model) {
            # Requirement 7.6: Automatically set tenant_id for all INSERT queries
            if (empty($model->tenant_id)) {
                $model->tenant_id = tenant_id();
            }
        });

        # Requirement 7.5: Automatically inject WHERE tenant_id = {current_tenant_id} into all SELECT queries
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (tenant_id()) {
                $builder->where('tenant_id', tenant_id());
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\ModularCore\Modules\Platform\Tenants\TenantModel::class, 'tenant_id');
    }
}

/**
 * Global Helper for Tenant Context (Requirement 7.4)
 */
if (!function_exists('tenant_id')) {
    function tenant_id() {
        return app('current_tenant_id');
    }
}
