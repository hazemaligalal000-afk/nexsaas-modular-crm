<?php

namespace ModularCore\Modules\Platform\CustomerPortal\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;

/**
 * Portal Dashboard Controller: Customer Visibility into CRM (Requirement F3)
 */
class PortalDashboardController extends Controller
{
    /**
     * Requirement 290: My Deals (Contacts)
     */
    public function getDeals(Request $request)
    {
        $contactId = $request->user()->id; // Assume Portal User ID = Contact ID
        $tenantId = $request->user()->tenant_id;

        $deals = \DB::table('deals')
            ->where('tenant_id', $tenantId)
            ->where('primary_contact_id', $contactId)
            ->select(['id', 'title', 'amount', 'stage', 'expected_close_date'])
            ->get();

        return response()->json($deals);
    }

    /**
     * Requirement 291: Documents Shared per Contact
     */
    public function getDocuments(Request $request)
    {
        $contactId = $request->user()->id;
        $tenantId = $request->user()->tenant_id;

        // Fetch files where shared_with = contact_id OR company_id
        $docs = \DB::table('portal_documents')
            ->where('tenant_id', $tenantId)
            ->where('contact_id', $contactId)
            ->get();

        return response()->json($docs);
    }

    /**
     * Requirement 292: Company Invoices (Client View)
     */
    public function getInvoices(Request $request)
    {
        $contactId = $request->user()->id;
        $tenantId = $request->user()->tenant_id;

        $companyId = \DB::table('contacts')->where('id', $contactId)->value('company_id');

        $invoices = \DB::table('invoices')
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->get();

        return response()->json($invoices);
    }
}
