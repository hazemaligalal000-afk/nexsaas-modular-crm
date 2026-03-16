<?php
/**
 * Modules/Partners/ApiController.php
 * Master Portal for Agencies & White-label Partners.
 */

namespace Modules\Partners;

use Core\TenantEnforcer;

class ApiController {
    
    public function getSubTenants() {
        $partnerTenantId = TenantEnforcer::getTenantId();
        
        // Return list of managed organization accounts
        return json_encode([
            'status' => 'success',
            'partner' => [
                'agency_name' => 'Growth Engines Inc.',
                'tier' => 'Platinum',
                'monthly_commission' => '$4,520.00'
            ],
            'clients' => [
                ['id' => 101, 'name' => 'Pizza Palace', 'status' => 'Active', 'mrr' => '$499'],
                ['id' => 102, 'name' => 'Law Office of Smith', 'status' => 'Active', 'mrr' => '$899'],
                ['id' => 105, 'name' => 'City Real Estate', 'status' => 'Pending Setup', 'mrr' => '$0']
            ]
        ]);
    }
}
