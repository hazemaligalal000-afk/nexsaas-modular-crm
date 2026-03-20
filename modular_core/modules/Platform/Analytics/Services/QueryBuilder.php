<?php

namespace ModularCore\Modules\Platform\Analytics\Services;

use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Analytics Query Builder: Dynamic SQL Generation for Custom Reports (Requirement F2)
 * Translates JSON configuration to safe parameterized SQL.
 */
class QueryBuilder
{
    private $allowedEntities = ['leads', 'deals', 'contacts', 'omnichannel_messages'];
    private $allowedAggregations = ['sum', 'count', 'avg', 'min', 'max'];

    /**
     * Requirement F2: Custom Report Builder SQL Generator
     */
    public function build($config, $tenantId)
    {
        $entity = $config['entity'] ?? 'leads';
        if (!in_array($entity, $this->allowedEntities)) {
            throw new Exception("Entity '{$entity}' is not permitted for reporting.");
        }

        $query = DB::table($entity)->where('tenant_id', $tenantId);

        // Apply Global Filters
        if (isset($config['filters'])) {
            $this->applyFilters($query, $config['filters']);
        }

        // Apply Aggregations
        $selects = [];
        if (isset($config['aggregations'])) {
            foreach ($config['aggregations'] as $field => $type) {
                if (in_array($type, $this->allowedAggregations)) {
                    $selects[] = DB::raw("{$type}({$field}) as {$field}_{$type}");
                }
            }
        }

        // Apply Group By
        if (isset($config['group_by'])) {
            $query->groupBy($config['group_by']);
            $selects[] = $config['group_by'];
        }

        if (empty($selects)) {
            $selects = ['*'];
        }

        $query->select($selects);

        // Sort
        if (isset($config['sort'])) {
            $query->orderBy($config['sort']['field'], $config['sort']['direction'] ?? 'desc');
        }

        return $query;
    }

    private function applyFilters($query, $filters)
    {
        foreach ($filters as $f) {
            $field = $f['field'];
            $op = $f['operator'];
            $val = $f['value'];

            switch ($op) {
                case 'eq': $query->where($field, $val); break;
                case 'neq': $query->where($field, '!=', $val); break;
                case 'gt': $query->where($field, '>', $val); break;
                case 'lt': $query->where($field, '<', $val); break;
                case 'contains': $query->where($field, 'LIKE', "%{$val}%"); break;
                case 'between': $query->whereBetween($field, $val); break;
            }
        }
    }
}
