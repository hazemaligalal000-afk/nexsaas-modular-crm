# ERP Invoicing Module

## Overview

The Invoicing module implements Accounts Receivable functionality with:
- Invoice creation and management
- PDF generation using mPDF
- Email delivery via SMTP
- Automatic AR journal entry posting
- Partial payment support
- Overdue invoice tracking
- Recurring invoice schedules
- Stripe payment integration

## Requirements Implemented

- **19.1**: Store Invoice records linked to an Account with line items, quantities, unit prices, tax rates, and discounts
- **19.2**: Auto-number Invoices with a configurable prefix and sequential number per Tenant
- **19.3**: When an Invoice is finalized, generate a PDF and send it to the customer's email address
- **19.4**: Support partial payments - when a payment is recorded against an Invoice, update the Invoice's outstanding balance
- **19.5**: When an Invoice's due date passes and the outstanding balance is greater than zero, mark the Invoice as overdue
- **19.6**: Support recurring Invoice schedules - when a schedule's next_run date is reached, auto-generate and send the Invoice
- **19.7**: Integrate with Stripe to accept online card payments against Invoices via a hosted payment link
- **19.8**: When a Stripe payment succeeds, automatically record the payment and update the Invoice status to paid within 60 seconds

## Database Tables

### invoices
- Stores invoice headers with customer information, totals, and status
- Auto-numbered with configurable prefix (e.g., INV-000001)
- Tracks payment status: draft, finalized, sent, partially_paid, paid, overdue, cancelled

### invoice_lines
- Line items with description, quantity, unit price, discounts, and taxes
- Linked to revenue accounts for journal entry posting

### payments
- Customer payments with multiple payment methods
- Supports Stripe integration with payment_intent_id and charge_id tracking

### payment_allocations
- Links payments to invoices for partial payment support
- Tracks allocated amounts per invoice

### recurring_invoice_schedules
- Configurable recurring invoice templates
- Supports daily, weekly, monthly, quarterly, and yearly frequencies
- Stores line items as JSON for template reuse

## API Endpoints

### GET /api/v1/erp/invoices
List all invoices with optional filters (status, account_id, is_overdue)

### GET /api/v1/erp/invoices/{id}
Get a single invoice by ID

### POST /api/v1/erp/invoices/{id}/finalize
Finalize an invoice:
1. Generate PDF using mPDF
2. Send email via SMTP
3. Post AR journal entry to General Ledger
4. Update invoice status to 'finalized'

### POST /api/v1/erp/invoices/{id}/payments
Record a payment against an invoice:
- Supports partial and full payments
- Updates outstanding balance
- Changes status to 'partially_paid' or 'paid'

### POST /api/v1/erp/invoices/{id}/payment-link
Create a Stripe hosted payment link for online card payments

### POST /api/billing/stripe/webhook
Stripe webhook handler for payment events:
- `invoice.payment_succeeded`: Records payment and updates invoice status within 60 seconds
- `invoice.payment_failed`: Logs failure for notification
- `payment_intent.succeeded`: Alternative payment success handler

## Services

### InvoiceService
Main service class implementing:
- `finalize(int $invoiceId, int $userId)`: Complete invoice finalization workflow
- `recordPayment(int $invoiceId, array $paymentData)`: Record and allocate payments
- `markOverdueInvoices()`: Batch update overdue invoices (run daily)
- `getNextInvoiceNumber(string $prefix)`: Auto-numbering with configurable prefix

### RecurringInvoiceTask (Celery)
Python Celery task that runs daily to:
1. Find active recurring schedules where next_run_date <= today
2. Generate invoices from templates
3. Update next_run_date based on frequency and interval

## Configuration

### SMTP Settings
```php
$config = [
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => 587,
    'smtp_user' => 'user@example.com',
    'smtp_password' => 'password',
    'smtp_from' => 'billing@nexsaas.com',
    'pdf_storage_path' => '/var/www/storage/invoices'
];
```

### Stripe Settings
```php
$_ENV['STRIPE_SECRET_KEY'] = 'sk_test_...';
$_ENV['STRIPE_WEBHOOK_SECRET'] = 'whsec_...';
```

## Journal Entry Posting

When an invoice is finalized, the following journal entry is posted:

**Debit**: Accounts Receivable (1200) - Total Amount
**Credit**: Revenue Accounts (per line item) - Line Subtotal - Discount
**Credit**: Tax Payable (2300) - Tax Amount

Currency conversion is handled automatically using the invoice's exchange rate.

## Recurring Invoices

To create a recurring invoice schedule:

1. Create a `recurring_invoice_schedules` record with:
   - `frequency`: daily|weekly|monthly|quarterly|yearly
   - `interval`: Number of periods (e.g., 2 for bi-weekly)
   - `start_date`: When to start generating
   - `next_run_date`: Next generation date
   - `line_items`: JSON array of line item templates

2. The RecurringInvoiceTask Celery task runs daily and:
   - Finds schedules where next_run_date <= today
   - Generates invoices in 'draft' status
   - Updates next_run_date based on frequency

3. Invoices can be finalized manually or automatically via workflow

## Stripe Integration

### Payment Flow
1. Create invoice in NexSaaS
2. Call `POST /api/v1/erp/invoices/{id}/payment-link` to generate Stripe payment link
3. Customer pays via Stripe hosted page
4. Stripe sends webhook to `/api/billing/stripe/webhook`
5. Webhook handler records payment and updates invoice status within 60 seconds

### Webhook Security
- Webhooks are verified using Stripe signature verification
- Invalid signatures are rejected with 400 status
- All webhook events are logged for audit trail

## Dependencies

- **mpdf/mpdf**: PDF generation
- **phpmailer/phpmailer**: Email delivery
- **stripe/stripe-php**: Stripe API integration
- **psycopg2** (Python): PostgreSQL access for Celery tasks

## Testing

Run the invoicing tests:
```bash
cd modular_core
vendor/bin/phpunit tests/ERP/Invoicing/
```

## Permissions

- `erp.invoice.view`: View invoices
- `erp.invoice.create`: Create invoices
- `erp.invoice.edit`: Edit draft invoices
- `erp.invoice.finalize`: Finalize invoices (generate PDF, send email, post journal entry)
- `erp.invoice.delete`: Soft-delete invoices
- `erp.payment.view`: View payments
- `erp.payment.create`: Record payments
- `erp.payment.edit`: Edit payments
- `erp.payment.delete`: Soft-delete payments
