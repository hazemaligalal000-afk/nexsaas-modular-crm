<?php
/**
 * ERP/Invoicing/InvoiceController.php
 *
 * REST API controller for invoice operations.
 */

declare(strict_types=1);

namespace Modules\ERP\Invoicing;

use Core\BaseController;

class InvoiceController extends BaseController
{
    private InvoiceService $invoiceService;
    private InvoiceModel $invoiceModel;
    
    public function __construct(InvoiceService $invoiceService, InvoiceModel $invoiceModel)
    {
        $this->invoiceService = $invoiceService;
        $this->invoiceModel = $invoiceModel;
    }
    
    /**
     * GET /api/v1/erp/invoices
     * List all invoices with optional filters
     */
    public function index(): Response
    {
        try {
            $filters = [
                'status' => $_GET['status'] ?? null,
                'account_id' => $_GET['account_id'] ?? null,
                'is_overdue' => $_GET['is_overdue'] ?? null,
                'limit' => $_GET['limit'] ?? 100
            ];
            
            $invoices = $this->invoiceModel->getInvoices(array_filter($filters));
            
            return $this->respond($invoices);
            
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/v1/erp/invoices/{id}
     * Get a single invoice
     */
    public function show(int $id): Response
    {
        try {
            $invoice = $this->invoiceModel->getInvoice($id);
            
            if (!$invoice) {
                return $this->respond(null, 'Invoice not found', 404);
            }
            
            return $this->respond($invoice);
            
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/v1/erp/invoices/{id}/finalize
     * Finalize an invoice (generate PDF, send email, post journal entry)
     */
    public function finalize(int $id): Response
    {
        try {
            $userId = (int)$this->userId;
            
            $result = $this->invoiceService->finalize($id, $userId);
            
            if (!$result['success']) {
                return $this->respond(null, $result['error'], 400);
            }
            
            return $this->respond($result['data']);
            
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/v1/erp/invoices/{id}/payments
     * Record a payment against an invoice
     */
    public function recordPayment(int $id): Response
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['amount']) || $input['amount'] <= 0) {
                return $this->respond(null, 'Valid payment amount is required', 400);
            }
            
            $paymentData = [
                'amount' => (float)$input['amount'],
                'payment_date' => $input['payment_date'] ?? date('Y-m-d'),
                'payment_method' => $input['payment_method'] ?? 'bank_transfer',
                'payment_reference' => $input['payment_reference'] ?? null,
                'notes' => $input['notes'] ?? null,
                'created_by' => (int)$this->userId
            ];
            
            $result = $this->invoiceService->recordPayment($id, $paymentData);
            
            if (!$result['success']) {
                return $this->respond(null, $result['error'], 400);
            }
            
            return $this->respond($result['data']);
            
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/v1/erp/invoices/{id}/payment-link
     * Create a Stripe payment link for an invoice
     */
    public function createPaymentLink(int $id): Response
    {
        try {
            $webhookController = new StripeWebhookController($this->invoiceService);
            $result = $webhookController->createPaymentLink($id);
            
            if (!$result['success']) {
                return $this->respond(null, $result['error'], 400);
            }
            
            return $this->respond($result['data']);
            
        } catch (\Exception $e) {
            return $this->respond(null, $e->getMessage(), 500);
        }
    }
}
