<?php
/**
 * ERP/Invoicing/StripeWebhookController.php
 *
 * Stripe webhook handler for invoice payment processing.
 *
 * Requirements: 19.7, 19.8
 * Task 20.4: Implement Stripe integration webhook handler
 */

declare(strict_types=1);

namespace Modules\ERP\Invoicing;

use Core\BaseController;

class StripeWebhookController extends BaseController
{
    private InvoiceService $invoiceService;
    private string $stripeWebhookSecret;
    
    public function __construct(InvoiceService $invoiceService, array $config = [])
    {
        $this->invoiceService = $invoiceService;
        $this->stripeWebhookSecret = $config['stripe_webhook_secret'] ?? '';
    }
    
    /**
     * Handle Stripe webhook events
     *
     * Endpoint: POST /api/billing/stripe/webhook
     *
     * Requirements: 19.7, 19.8
     *
     * @return Response
     */
    public function handleWebhook(): Response
    {
        try {
            // Get raw POST body
            $payload = file_get_contents('php://input');
            $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
            
            // Verify webhook signature
            $event = $this->verifyWebhookSignature($payload, $sigHeader);
            
            if (!$event) {
                return $this->respond(
                    null,
                    'Invalid webhook signature',
                    400
                );
            }
            
            // Log webhook event
            error_log(sprintf(
                'Stripe webhook received: %s (ID: %s)',
                $event->type,
                $event->id
            ));
            
            // Handle event based on type
            $result = match ($event->type) {
                'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($event),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event),
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event),
                default => ['success' => true, 'data' => ['message' => 'Event type not handled'], 'error' => null]
            };
            
            if (!$result['success']) {
                return $this->respond(null, $result['error'], 500);
            }
            
            return $this->respond($result['data'], null, 200);
            
        } catch (\Exception $e) {
            error_log('Stripe webhook error: ' . $e->getMessage());
            return $this->respond(
                null,
                'Webhook processing failed: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Handle invoice.payment_succeeded event
     *
     * Requirement 19.8: When a Stripe payment succeeds, automatically record 
     * the payment and update the Invoice status to paid within 60 seconds.
     *
     * @param object $event
     * @return array{success: bool, data: array|null, error: string|null}
     */
    private function handleInvoicePaymentSucceeded(object $event): array
    {
        $startTime = microtime(true);
        
        try {
            $invoice = $event->data->object;
            
            // Extract metadata
            $invoiceId = $invoice->metadata->invoice_id ?? null;
            $tenantId = $invoice->metadata->tenant_id ?? null;
            $companyCode = $invoice->metadata->company_code ?? '01';
            
            if (!$invoiceId) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Invoice ID not found in Stripe invoice metadata'
                ];
            }
            
            // Extract payment details
            $amountPaid = $invoice->amount_paid / 100;  // Convert from cents
            $currency = strtoupper($invoice->currency);
            $paymentIntentId = $invoice->payment_intent ?? null;
            $chargeId = $invoice->charge ?? null;
            
            // Record payment
            $paymentData = [
                'amount' => $amountPaid,
                'currency_code' => $currency,
                'payment_method' => 'stripe',
                'payment_reference' => $invoice->id,
                'stripe_payment_intent_id' => $paymentIntentId,
                'stripe_charge_id' => $chargeId,
                'payment_date' => date('Y-m-d', $invoice->status_transitions->paid_at ?? time()),
                'notes' => sprintf('Stripe payment for invoice %s', $invoice->number),
                'created_by' => null  // System-generated
            ];
            
            $result = $this->invoiceService->recordPayment((int)$invoiceId, $paymentData);
            
            $elapsedTime = microtime(true) - $startTime;
            
            // Log processing time (should be < 60 seconds per Req 19.8)
            error_log(sprintf(
                'Stripe payment processed in %.2f seconds for invoice %s',
                $elapsedTime,
                $invoiceId
            ));
            
            if ($elapsedTime > 60) {
                error_log(sprintf(
                    'WARNING: Stripe payment processing exceeded 60 seconds (%.2f s) for invoice %s',
                    $elapsedTime,
                    $invoiceId
                ));
            }
            
            return $result;
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to process payment: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle invoice.payment_failed event
     *
     * @param object $event
     * @return array{success: bool, data: array|null, error: string|null}
     */
    private function handleInvoicePaymentFailed(object $event): array
    {
        try {
            $invoice = $event->data->object;
            $invoiceId = $invoice->metadata->invoice_id ?? null;
            
            if (!$invoiceId) {
                return [
                    'success' => true,
                    'data' => ['message' => 'Invoice ID not found in metadata'],
                    'error' => null
                ];
            }
            
            // Log payment failure
            error_log(sprintf(
                'Stripe payment failed for invoice %s: %s',
                $invoiceId,
                $invoice->last_finalization_error->message ?? 'Unknown error'
            ));
            
            // TODO: Implement notification to customer about payment failure
            // TODO: Update invoice status or add note about failed payment attempt
            
            return [
                'success' => true,
                'data' => ['message' => 'Payment failure logged'],
                'error' => null
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to process payment failure: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle payment_intent.succeeded event
     *
     * @param object $event
     * @return array{success: bool, data: array|null, error: string|null}
     */
    private function handlePaymentIntentSucceeded(object $event): array
    {
        try {
            $paymentIntent = $event->data->object;
            
            // Extract metadata
            $invoiceId = $paymentIntent->metadata->invoice_id ?? null;
            
            if (!$invoiceId) {
                return [
                    'success' => true,
                    'data' => ['message' => 'Invoice ID not found in metadata'],
                    'error' => null
                ];
            }
            
            // Extract payment details
            $amountPaid = $paymentIntent->amount / 100;  // Convert from cents
            $currency = strtoupper($paymentIntent->currency);
            
            // Record payment
            $paymentData = [
                'amount' => $amountPaid,
                'currency_code' => $currency,
                'payment_method' => 'stripe',
                'payment_reference' => $paymentIntent->id,
                'stripe_payment_intent_id' => $paymentIntent->id,
                'stripe_charge_id' => $paymentIntent->charges->data[0]->id ?? null,
                'payment_date' => date('Y-m-d'),
                'notes' => sprintf('Stripe PaymentIntent %s', $paymentIntent->id),
                'created_by' => null  // System-generated
            ];
            
            return $this->invoiceService->recordPayment((int)$invoiceId, $paymentData);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to process payment intent: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify Stripe webhook signature
     *
     * @param string $payload
     * @param string $sigHeader
     * @return object|null Event object if valid, null otherwise
     */
    private function verifyWebhookSignature(string $payload, string $sigHeader): ?object
    {
        if (empty($this->stripeWebhookSecret)) {
            error_log('WARNING: Stripe webhook secret not configured, skipping signature verification');
            return json_decode($payload);
        }
        
        try {
            // Use Stripe SDK to verify signature
            \Stripe\Stripe::setApiKey($this->getStripeApiKey());
            
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $this->stripeWebhookSecret
            );
            
            return $event;
            
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            error_log('Stripe webhook signature verification failed: Invalid payload');
            return null;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            error_log('Stripe webhook signature verification failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get Stripe API key from configuration
     *
     * @return string
     */
    private function getStripeApiKey(): string
    {
        // In production, this should come from environment variables or secure config
        return $_ENV['STRIPE_SECRET_KEY'] ?? '';
    }
    
    /**
     * Create a Stripe payment link for an invoice
     *
     * Requirement 19.7: Integrate with Stripe to accept online card payments 
     * against Invoices via a hosted payment link.
     *
     * @param int $invoiceId
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function createPaymentLink(int $invoiceId): array
    {
        try {
            \Stripe\Stripe::setApiKey($this->getStripeApiKey());
            
            // Get invoice details
            $invoice = $this->getInvoiceById($invoiceId);
            if (!$invoice) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Invoice {$invoiceId} not found"
                ];
            }
            
            // Create Stripe Price
            $price = \Stripe\Price::create([
                'unit_amount' => (int)($invoice['total_amount'] * 100),  // Convert to cents
                'currency' => strtolower($invoice['currency_code']),
                'product_data' => [
                    'name' => sprintf('Invoice %s', $invoice['invoice_no']),
                    'description' => sprintf('Payment for invoice %s', $invoice['invoice_no'])
                ],
            ]);
            
            // Create Payment Link
            $paymentLink = \Stripe\PaymentLink::create([
                'line_items' => [
                    [
                        'price' => $price->id,
                        'quantity' => 1,
                    ],
                ],
                'metadata' => [
                    'invoice_id' => $invoiceId,
                    'invoice_no' => $invoice['invoice_no'],
                    'tenant_id' => $this->tenantId,
                    'company_code' => $this->companyCode
                ],
                'after_completion' => [
                    'type' => 'redirect',
                    'redirect' => [
                        'url' => sprintf(
                            '%s/invoices/%d/payment-success',
                            $_ENV['APP_URL'] ?? 'https://app.nexsaas.com',
                            $invoiceId
                        )
                    ]
                ]
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'payment_link_url' => $paymentLink->url,
                    'payment_link_id' => $paymentLink->id
                ],
                'error' => null
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to create payment link: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get invoice by ID (helper method)
     *
     * @param int $invoiceId
     * @return array|null
     */
    private function getInvoiceById(int $invoiceId): ?array
    {
        // This would typically use the InvoiceService or a model
        // For now, direct DB query
        $db = $this->getDb();
        
        $sql = "SELECT * FROM invoices 
                WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL";
        
        $result = $db->Execute($sql, [
            $invoiceId,
            $this->tenantId,
            $this->companyCode
        ]);
        
        if (!$result || $result->EOF) {
            return null;
        }
        
        return $result->fields;
    }
    
    /**
     * Get database connection (placeholder - should be injected)
     *
     * @return \ADOConnection
     */
    private function getDb()
    {
        // In production, this should be injected via dependency injection
        global $db;
        return $db;
    }
}
