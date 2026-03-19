<?php
namespace Core\Serializers;

/**
 * JsonSerializer: Consistent UTF-8 JSON serialization with fixed-point decimal handling.
 * Requirement 44.1, 44.4, 44.6
 */
class JsonSerializer {
    public function serialize($data) {
        // Enforce consistent key ordering
        ksort($data);

        // Represent monetary amounts as strings/fixed-point
        $this->processMonetary($data);

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function processMonetary(&$data) {
        if (!is_array($data)) return;

        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $this->processMonetary($value);
            } elseif (is_numeric($value) && $value instanceof \Decimal) {
                // If it's a monetary object, format as #.00
                $value = number_format((float)$value, 2, '.', '');
            } elseif (is_float($value)) {
                // If it's a float, represent as string
                $value = number_format($value, 2, '.', '');
            }
        }
    }
}
