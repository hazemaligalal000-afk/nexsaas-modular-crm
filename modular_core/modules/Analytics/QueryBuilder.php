<?php

namespace ModularCore\Modules\Analytics;

/**
 * Advanced Analytics Query Builder (F2 Roadmap)
 * 
 * Safely translates report configuration JSON into parameterized SQL.
 */
class QueryBuilder
{
    private $allowedEntities = ['leads', 'contacts', 'deals', 'activities', 'emails', 'invoices'];
    private $allowedOperators = ['eq', 'not_eq', 'gt', 'lt', 'between', 'contains', 'starts_with'];
    private $params = [];

    /**
     * Build SQL from Config JSON
     */
    public function build($configJson)
    {
        $config = json_decode($configJson, true);
        if (!$config) throw new \Exception("Invalid report configuration");

        $entity = strtolower($config['entity']);
        if (!in_array($entity, $this->allowedEntities)) {
            throw new \Exception("Entity '{$entity}' not allowed for reporting.");
        }

        $fields = $this->sanitizeFields($config['fields']);
        $sql = "SELECT " . implode(", ", $fields);

        // Aggregations
        if (isset($config['aggregations'])) {
            foreach ($config['aggregations'] as $field => $func) {
                $sql .= ", " . strtoupper($func) . "({$field}) as {$field}_{$func}";
            }
        }

        $sql .= " FROM {$entity}";

        // Filter Clause
        if (isset($config['filters']) && count($config['filters']) > 0) {
            $sql .= " WHERE " . $this->buildFilters($config['filters']);
        }

        // Grouping
        if (isset($config['group_by'])) {
            $sql .= " GROUP BY " . $this->sanitizeField($config['group_by']);
        }

        // Sorting
        if (isset($config['sort'])) {
            $dir = (strtoupper($config['sort']['direction']) === 'DESC') ? 'DESC' : 'ASC';
            $sql .= " ORDER BY " . $this->sanitizeField($config['sort']['field']) . " {$dir}";
        }

        return ['sql' => $sql, 'params' => $this->params];
    }

    private function buildFilters($filters)
    {
        $parts = [];
        foreach ($filters as $i => $f) {
            $field = $this->sanitizeField($f['field']);
            $op = $f['operator'];
            $valKey = "p_{$i}";

            switch ($op) {
                case 'eq': $parts[] = "{$field} = :{$valKey}"; break;
                case 'not_eq': $parts[] = "{$field} != :{$valKey}"; break;
                case 'gt': $parts[] = "{$field} > :{$valKey}"; break;
                case 'lt': $parts[] = "{$field} < :{$valKey}"; break;
                case 'between': 
                    $parts[] = "{$field} BETWEEN :{$valKey}_0 AND :{$valKey}_1";
                    $this->params["{$valKey}_0"] = $f['value'][0];
                    $this->params["{$valKey}_1"] = $f['value'][1];
                    continue 2;
                case 'contains': 
                    $parts[] = "{$field} LIKE :{$valKey}"; 
                    $f['value'] = "%" . $f['value'] . "%";
                    break;
            }
            $this->params[$valKey] = $f['value'];
        }
        return implode(" AND ", $parts);
    }

    private function sanitizeFields($fields) {
        return array_map([$this, 'sanitizeField'], $fields);
    }

    private function sanitizeField($field) {
        return preg_replace('/[^a-z0-9_]/', '', $field);
    }
}
