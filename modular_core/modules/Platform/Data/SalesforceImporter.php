<?php
/**
 * ModularCore/Modules/Platform/Data/SalesforceImporter.php
 * Automated Salesforce .CSV & API Migration Tool - Requirement 10.4
 */

namespace ModularCore\Modules\Platform\Data;

class SalesforceImporter {
    private $tenantId;

    public function __construct(int $tenantId) {
        $this->tenantId = $tenantId;
    }

    /**
     * Parse and import standard Salesforce Accounts/Contacts/Leads CSV
     */
    public function parseSalesforceCSV(string $filePath, string $entityType) {
        if (!file_exists($filePath)) throw new \Exception("CSV Upload Failure.");
        
        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle);
        
        // Normalization mapping from SF standard to NexSaaS standard
        $mapping = [
            'AccountId' => 'external_sf_id',
            'Name' => 'company_name',
            'Industry' => 'industry',
            'AnnualRevenue' => 'annual_revenue',
            'BillingCity' => 'city',
            'ContactId' => 'external_sf_id',
            'Email' => 'email',
            'Phone' => 'phone'
        ];

        $imported = 0;
        $pdo = \Core\Database::getTenantConnection();
        $pdo->beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $record = array_combine($header, $row);
                $normalized = [];

                foreach ($record as $sfKey => $value) {
                    if (isset($mapping[$sfKey])) {
                        $normalized[$mapping[$sfKey]] = $value;
                    }
                }

                if ($entityType === 'leads') {
                    $this->insertLead($pdo, $normalized);
                } elseif ($entityType === 'accounts') {
                    $this->insertAccount($pdo, $normalized);
                }

                $imported++;
            }
            $pdo->commit();
            return $imported;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw new \Exception("Migration Failed at row $imported: " . $e->getMessage());
        } finally {
            fclose($handle);
        }
    }

    private function insertLead($pdo, array $data) {
        $stmt = $pdo->prepare("INSERT INTO leads (tenant_id, email, phone, custom_fields) VALUES (?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $data['email'] ?? null, $data['phone'] ?? null, json_encode($data)]);
    }

    private function insertAccount($pdo, array $data) {
        $stmt = $pdo->prepare("INSERT INTO accounts (tenant_id, account_name, industry) VALUES (?, ?, ?)");
        $stmt->execute([$this->tenantId, $data['company_name'] ?? 'Unknown', $data['industry'] ?? null]);
    }
}
