<?php
/**
 * Accounting/LookupController.php
 * 
 * Provides searchable lists for the Journal Entry form.
 */

declare(strict_types=1);

namespace Modules\Accounting;

use Core\BaseController;
use Modules\Platform\Auth\AuthMiddleware;

class LookupController extends BaseController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * GET /api/v1/accounting/lookups/accounts
     */
    public function accounts($request): Response
    {
        $q = $request['queries']['q'] ?? '';
        $sql = "SELECT account_code as code, account_name_en as name_en, account_name_ar as name_ar 
                FROM chart_of_accounts 
                WHERE (account_code LIKE ? OR account_name_en ILIKE ? OR account_name_ar LIKE ?)
                  AND allow_posting = TRUE AND deleted_at IS NULL
                LIMIT 20";
        $results = $this->db->GetAll($sql, ["%$q%", "%$q%", "%$q%"]);
        return $this->respond($results);
    }

    /**
     * GET /api/v1/accounting/lookups/cost-centers
     */
    public function costCenters($request): Response
    {
        $q = $request['queries']['q'] ?? '';
        $sql = "SELECT code, name FROM cost_centers 
                WHERE (code LIKE ? OR name ILIKE ?) AND deleted_at IS NULL
                LIMIT 20";
        $results = $this->db->GetAll($sql, ["%$q%", "%$q%"]);
        return $this->respond($results);
    }

    /**
     * GET /api/v1/accounting/lookups/partners
     */
    public function partners($request): Response
    {
        $q = $request['queries']['q'] ?? '';
        $sql = "SELECT partner_code as code, partner_name as name FROM partners 
                WHERE (partner_code LIKE ? OR partner_name ILIKE ?) AND deleted_at IS NULL
                LIMIT 20";
        $results = $this->db->GetAll($sql, ["%$q%", "%$q%"]);
        return $this->respond($results);
    }
}
