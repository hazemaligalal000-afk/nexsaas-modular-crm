<?php
/**
 * Platform/Migrations/SalesforceMigrator.php
 * 
 * Enterprise-grade migration utility for Salesforce -> NexSaaS (Requirement 10.4)
 */

namespace NexSaaS\Platform\Migrations;

class SalesforceMigrator
{
    private $adb;

    public function __construct($adb) {
        $this->adb = $adb;
    }

    /**
     * Map Salesforce JSON to NexSaaS Core Entities
     */
    public function migrate(int $tenantId, array $sfData): array
    {
        $stats = ['leads' => 0, 'contacts' => 0, 'accounts' => 0];

        foreach ($sfData['records'] as $record) {
            switch ($record['attributes']['type']) {
                case 'Lead':
                    $this->importLead($tenantId, $record);
                    $stats['leads']++;
                    break;
                case 'Contact':
                    $this->importContact($tenantId, $record);
                    $stats['contacts']++;
                    break;
            }
        }

        return $stats;
    }

    private function importLead($tenantId, $data) {
        // Mapping Logic: Salesforce 'Company' -> NexSaaS 'company'
        $sql = "INSERT INTO vtiger_leaddetails (firstname, lastname, company, leadsource, organization_id) VALUES (?, ?, ?, ?, ?)";
        $this->adb->pquery($sql, [
            $data['FirstName'] ?? 'SF_Import',
            $data['LastName'] ?? 'Unknown',
            $data['Company'] ?? '',
            'Salesforce Migration',
            $tenantId
        ]);
    }

    private function importContact($tenantId, $data) {
        $sql = "INSERT INTO vtiger_contactdetails (firstname, lastname, organization_id) VALUES (?, ?, ?)";
        $this->adb->pquery($sql, [
            $data['FirstName'],
            $data['LastName'],
            $tenantId
        ]);
    }
}
