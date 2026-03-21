<?php
/**
 * ModularCore/Modules/Platform/Reporting/Services/CustomReportGenerator.php
 * Dynamic CSV/JSON Report Engine (Requirement 10.9)
 */

namespace ModularCore\Modules\Platform\Reporting\Services;

use Core\Database;

class CustomReportGenerator {
    
    /**
     * Export Table to CSV for specific tenant
     */
    public function exportToCSV(int $tenantId, string $entity = 'leads', array $fields = ['first_name', 'email', 'phone', 'source']) {
        // Enforce entity whitelist for SQL safety
        $whitelist = ['leads', 'deals', 'accounts', 'conversations'];
        if (!in_array($entity, $whitelist)) throw new \Exception("Invalid report entity.");

        $pdo = Database::getTenantConnection();
        $cols = implode(", ", $fields);
        
        $sql = "SELECT {$cols} FROM {$entity} WHERE tenant_id = ? AND deleted_at IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        
        $fp = fopen('php://memory', 'w');
        
        // Add header
        fputcsv($fp, $fields);
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            fputcsv($fp, $row);
        }
        
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        
        return $csv;
    }

    /**
     * Generate Performance PDF (Placeholder for DomPDF/Snappy)
     */
    public function generatePerformancePDF(int $tenantId) {
        // Mock generation
        return "PDF_BINARY_REPRESENTATION_FOR_TENANT_" . $tenantId;
    }
}
