<?php

namespace ModularCore\Modules\Saudi\Zatca;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use ModularCore\Modules\Saudi\Zatca\ZatcaFatoorahService;
use Exception;

class ZatcaController extends Controller
{
    private $zatcaService;

    public function __construct(ZatcaFatoorahService $zatcaService)
    {
        $this->zatcaService = $zatcaService;
    }

    /**
     * POST /api/v1/saudi/zatca/report
     * Reports an invoice to ZATCA for clearance or reporting
     */
    public function reportInvoice(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required',
            'amount' => 'required|numeric'
        ]);

        try {
            # In Production, lookup original invoice record from business DB
            $invoiceData = [
                'uuid' => \Str::uuid(),
                'invoice_number' => 'INV-2026-0001',
                'issue_date' => now()->toDateString(),
                'amount' => $request->amount,
                'certificate' => env('ZATCA_CLIENT_CERT')
            ];

            # 1. Coordinate with ZATCA Service
            $result = $this->zatcaService.generateAndReport($invoiceData);

            # 2. Update Invoice to 'REPORTED' and store QR
            # \DB::table('invoices')->where('id', $request->invoice_id)->update([...]);

            return response()->json([
                'success' => true,
                'zatca_result' => $result
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
