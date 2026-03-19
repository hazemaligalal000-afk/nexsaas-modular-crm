<?php
/**
 * ERP/Invoicing/InvoiceService.php
 *
 * Invoice Service implementing:
 * - Invoice finalization with PDF generation (mPDF)
 * - Email delivery via SMTP
 * - AR journal entry posting
 * - Auto-numbering with configurable prefix
 * - Partial payment support
 * - Overdue marking
 *
 * Requirements: 19.1, 19.2, 19.3, 19.4, 19.5
 */

declare(strict_types=1);

namespace Modules\ERP\Invoicing;

use Core\BaseModel;
use Modules\ERP\GL\JournalEntryService;

class InvoiceService
{
    private BaseModel $model;
    private JournalEntryService $journalService;
    private string $smtpHost;
    private int $smtpPort;
    private string $smtpUser;
    private string $smtpPassword;
    private string $smtpFrom;
    private string $pdfStoragePath;

    public function __construct(
        BaseModel $model,
        JournalEntryService $journalService,
        array $config = []
    ) {
        $this->model = $model;
        $this->journalService = $journalService;
        
        // SMTP configuration
        $this->smtpHost = $config['smtp_host'] ?? 'localhost';
        $this->smtpPort = $config['smtp_port'] ?? 587;
        $this->smtpUser = $config['smtp_user'] ?? '';
        $this->smtpPassword = $config['smtp_password'] ?? '';
        $this->smtpFrom = $config['smtp_from'] ?? 'noreply@nexsaas.com';
        
        // PDF storage path
        $this->pdfStoragePath = $config['pdf_storage_path'] ?? '/var/www/storage/invoices';
    }

    /**
     * Finalize an invoice: generate PDF, send email, post AR journal entry
     *
     * Requirements: 19.2, 19.3, 19.4
     *
     * @param int $invoiceId
     * @param int $userId User performing the action
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function finalize(int $invoiceId, int $userId): array
    {
        $db = $this->model->getDb();
        
        // Start transaction
        $db->StartTrans();
        
        try {
            // Fetch invoice with lines
            $invoice = $this->getInvoiceById($invoiceId);
            if (!$invoice) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Invoice {$invoiceId} not found"
                ];
            }
            
            // Check if already finalized
            if ($invoice['status'] !== 'draft') {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Invoice {$invoice['invoice_no']} is already {$invoice['status']}"
                ];
            }
            
            // Get invoice lines
            $lines = $this->getInvoiceLines($invoiceId);
            if (empty($lines)) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Invoice {$invoice['invoice_no']} has no line items"
                ];
            }
            
            // Generate PDF
            $pdfResult = $this->generatePDF($invoice, $lines);
            if (!$pdfResult['success']) {
                $db->RollbackTrans();
                return $pdfResult;
            }
            
            $pdfPath = $pdfResult['data']['pdf_path'];
            
            // Send email
            $emailResult = $this->sendInvoiceEmail($invoice, $pdfPath);
            if (!$emailResult['success']) {
                $db->RollbackTrans();
                return $emailResult;
            }
            
            // Post AR journal entry
            $jeResult = $this->postARJournalEntry($invoice, $lines, $userId);
            if (!$jeResult['success']) {
                $db->RollbackTrans();
                return $jeResult;
            }
            
            // Update invoice status
            $updateSql = "UPDATE invoices 
                         SET status = 'finalized',
                             pdf_path = ?,
                             sent_at = NOW(),
                             sent_to = ?,
                             journal_entry_id = ?,
                             outstanding_balance = total_amount,
                             updated_at = NOW()
                         WHERE id = ? AND tenant_id = ? AND company_code = ?";
            
            $result = $db->Execute($updateSql, [
                $pdfPath,
                $invoice['customer_email'],
                $jeResult['data']['je_header_id'],
                $invoiceId,
                $this->model->getTenantId(),
                $this->model->getCompanyCode()
            ]);
            
            if ($result === false) {
                throw new \RuntimeException('Failed to update invoice status: ' . $db->ErrorMsg());
            }
            
            $db->CompleteTrans();
            
            return [
                'success' => true,
                'data' => [
                    'invoice_id' => $invoiceId,
                    'invoice_no' => $invoice['invoice_no'],
                    'pdf_path' => $pdfPath,
                    'sent_to' => $invoice['customer_email'],
                    'journal_entry_id' => $jeResult['data']['je_header_id']
                ],
                'error' => null
            ];
            
        } catch (\Exception $e) {
            $db->RollbackTrans();
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to finalize invoice: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Record a payment against an invoice (partial or full)
     *
     * Requirement: 19.4
     *
     * @param int $invoiceId
     * @param array $paymentData
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function recordPayment(int $invoiceId, array $paymentData): array
    {
        $db = $this->model->getDb();
        $db->StartTrans();
        
        try {
            // Fetch invoice
            $invoice = $this->getInvoiceById($invoiceId);
            if (!$invoice) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Invoice {$invoiceId} not found"
                ];
            }
            
            $amount = (float)$paymentData['amount'];
            $outstandingBalance = (float)$invoice['outstanding_balance'];
            
            // Validate payment amount
            if ($amount <= 0) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Payment amount must be greater than zero'
                ];
            }
            
            if ($amount > $outstandingBalance) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => sprintf(
                        'Payment amount (%.2f) exceeds outstanding balance (%.2f)',
                        $amount,
                        $outstandingBalance
                    )
                ];
            }
            
            // Get next payment number
            $paymentNo = $this->getNextPaymentNumber();
            
            // Insert payment record
            $paymentId = $this->insertPayment([
                'payment_no' => $paymentNo,
                'payment_date' => $paymentData['payment_date'] ?? date('Y-m-d'),
                'amount' => $amount,
                'currency_code' => $invoice['currency_code'],
                'exchange_rate' => $invoice['exchange_rate'],
                'amount_base' => $amount * $invoice['exchange_rate'],
                'payment_method' => $paymentData['payment_method'] ?? 'bank_transfer',
                'payment_reference' => $paymentData['payment_reference'] ?? null,
                'account_id' => $invoice['account_id'],
                'customer_name' => $invoice['customer_name'],
                'allocated_amount' => $amount,
                'unallocated_amount' => 0,
                'status' => 'cleared',
                'notes' => $paymentData['notes'] ?? null,
                'created_by' => $paymentData['created_by'] ?? null
            ]);
            
            // Create payment allocation
            $this->insertPaymentAllocation([
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'allocated_amount' => $amount,
                'allocation_date' => $paymentData['payment_date'] ?? date('Y-m-d'),
                'created_by' => $paymentData['created_by'] ?? null
            ]);
            
            // Update invoice
            $newOutstanding = $outstandingBalance - $amount;
            $newPaidAmount = (float)$invoice['paid_amount'] + $amount;
            $newStatus = ($newOutstanding <= 0.01) ? 'paid' : 'partially_paid';
            
            $updateSql = "UPDATE invoices 
                         SET paid_amount = ?,
                             outstanding_balance = ?,
                             status = ?,
                             is_overdue = false,
                             updated_at = NOW()
                         WHERE id = ? AND tenant_id = ? AND company_code = ?";
            
            $result = $db->Execute($updateSql, [
                $newPaidAmount,
                $newOutstanding,
                $newStatus,
                $invoiceId,
                $this->model->getTenantId(),
                $this->model->getCompanyCode()
            ]);
            
            if ($result === false) {
                throw new \RuntimeException('Failed to update invoice: ' . $db->ErrorMsg());
            }
            
            $db->CompleteTrans();
            
            return [
                'success' => true,
                'data' => [
                    'payment_id' => $paymentId,
                    'payment_no' => $paymentNo,
                    'amount' => $amount,
                    'new_outstanding_balance' => $newOutstanding,
                    'invoice_status' => $newStatus
                ],
                'error' => null
            ];
            
        } catch (\Exception $e) {
            $db->RollbackTrans();
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to record payment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Mark overdue invoices
     *
     * Requirement: 19.5
     *
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function markOverdueInvoices(): array
    {
        try {
            $sql = "UPDATE invoices 
                   SET is_overdue = true, updated_at = NOW()
                   WHERE tenant_id = ? 
                   AND company_code = ?
                   AND deleted_at IS NULL
                   AND due_date < CURRENT_DATE
                   AND outstanding_balance > 0
                   AND status NOT IN ('paid', 'cancelled')
                   AND is_overdue = false";
            
            $db = $this->model->getDb();
            $result = $db->Execute($sql, [
                $this->model->getTenantId(),
                $this->model->getCompanyCode()
            ]);
            
            if ($result === false) {
                throw new \RuntimeException('Failed to mark overdue invoices: ' . $db->ErrorMsg());
            }
            
            $affectedRows = $db->Affected_Rows();
            
            return [
                'success' => true,
                'data' => ['marked_overdue_count' => $affectedRows],
                'error' => null
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to mark overdue invoices: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate PDF for invoice using mPDF
     *
     * Requirement: 19.3
     *
     * @param array $invoice
     * @param array $lines
     * @return array{success: bool, data: array|null, error: string|null}
     */
    private function generatePDF(array $invoice, array $lines): array
    {
        try {
            // Ensure storage directory exists
            if (!is_dir($this->pdfStoragePath)) {
                mkdir($this->pdfStoragePath, 0755, true);
            }
            
            // Generate filename
            $filename = sprintf(
                'invoice_%s_%s.pdf',
                $invoice['invoice_no'],
                date('YmdHis')
            );
            $fullPath = $this->pdfStoragePath . '/' . $filename;
            
            // Create mPDF instance
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 20,
                'margin_bottom' => 20,
            ]);
            
            // Generate HTML content
            $html = $this->generateInvoiceHTML($invoice, $lines);
            
            // Write HTML to PDF
            $mpdf->WriteHTML($html);
            
            // Save PDF
            $mpdf->Output($fullPath, \Mpdf\Output\Destination::FILE);
            
            return [
                'success' => true,
                'data' => ['pdf_path' => $fullPath],
                'error' => null
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to generate PDF: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate HTML content for invoice PDF
     *
     * @param array $invoice
     * @param array $lines
     * @return string
     */
    private function generateInvoiceHTML(array $invoice, array $lines): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #333; }
        .info-section { margin-bottom: 20px; }
        .info-section table { width: 100%; }
        .info-section td { padding: 5px; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .items-table th { background-color: #f0f0f0; padding: 10px; text-align: left; border: 1px solid #ddd; }
        .items-table td { padding: 8px; border: 1px solid #ddd; }
        .totals { margin-top: 20px; float: right; width: 300px; }
        .totals table { width: 100%; }
        .totals td { padding: 5px; }
        .totals .total-row { font-weight: bold; font-size: 14px; border-top: 2px solid #333; }
        .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>INVOICE</h1>
        <p>Invoice No: ' . htmlspecialchars($invoice['invoice_no']) . '</p>
    </div>
    
    <div class="info-section">
        <table>
            <tr>
                <td width="50%">
                    <strong>Bill To:</strong><br>
                    ' . htmlspecialchars($invoice['customer_name']) . '<br>
                    ' . htmlspecialchars($invoice['customer_email'] ?? '') . '
                </td>
                <td width="50%" style="text-align: right;">
                    <strong>Invoice Date:</strong> ' . htmlspecialchars($invoice['invoice_date']) . '<br>
                    <strong>Due Date:</strong> ' . htmlspecialchars($invoice['due_date']) . '<br>
                    <strong>Currency:</strong> ' . htmlspecialchars($invoice['currency_code']) . '
                </td>
            </tr>
        </table>
    </div>
    
    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="45%">Description</th>
                <th width="10%">Qty</th>
                <th width="15%">Unit Price</th>
                <th width="10%">Tax</th>
                <th width="15%">Total</th>
            </tr>
        </thead>
        <tbody>';
        
        foreach ($lines as $line) {
            $html .= '<tr>
                <td>' . htmlspecialchars($line['line_no']) . '</td>
                <td>' . htmlspecialchars($line['description']) . '</td>
                <td>' . number_format($line['quantity'], 2) . '</td>
                <td>' . number_format($line['unit_price'], 2) . '</td>
                <td>' . number_format($line['tax_amount'], 2) . '</td>
                <td>' . number_format($line['line_total'], 2) . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
    </table>
    
    <div class="totals">
        <table>
            <tr>
                <td>Subtotal:</td>
                <td style="text-align: right;">' . number_format($invoice['subtotal'], 2) . '</td>
            </tr>
            <tr>
                <td>Tax:</td>
                <td style="text-align: right;">' . number_format($invoice['tax_amount'], 2) . '</td>
            </tr>
            <tr>
                <td>Discount:</td>
                <td style="text-align: right;">-' . number_format($invoice['discount_amount'], 2) . '</td>
            </tr>
            <tr class="total-row">
                <td>Total:</td>
                <td style="text-align: right;">' . number_format($invoice['total_amount'], 2) . '</td>
            </tr>
        </table>
    </div>
    
    <div style="clear: both;"></div>
    
    <div class="footer">
        <p>Thank you for your business!</p>
        ' . (!empty($invoice['terms_and_conditions']) ? '<p>' . htmlspecialchars($invoice['terms_and_conditions']) . '</p>' : '') . '
    </div>
</body>
</html>';
        
        return $html;
    }

    /**
     * Send invoice email via SMTP
     *
     * Requirement: 19.3
     *
     * @param array $invoice
     * @param string $pdfPath
     * @return array{success: bool, data: array|null, error: string|null}
     */
    private function sendInvoiceEmail(array $invoice, string $pdfPath): array
    {
        try {
            if (empty($invoice['customer_email'])) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Customer email address is required'
                ];
            }
            
            // Create PHPMailer instance
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // SMTP configuration
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = !empty($this->smtpUser);
            $mail->Username = $this->smtpUser;
            $mail->Password = $this->smtpPassword;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtpPort;
            
            // Recipients
            $mail->setFrom($this->smtpFrom, 'NexSaaS Billing');
            $mail->addAddress($invoice['customer_email'], $invoice['customer_name']);
            
            // Attach PDF
            $mail->addAttachment($pdfPath, basename($pdfPath));
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = sprintf('Invoice %s from NexSaaS', $invoice['invoice_no']);
            $mail->Body = $this->generateEmailBody($invoice);
            
            // Send
            $mail->send();
            
            return [
                'success' => true,
                'data' => ['sent_to' => $invoice['customer_email']],
                'error' => null
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate email body for invoice
     *
     * @param array $invoice
     * @return string
     */
    private function generateEmailBody(array $invoice): string
    {
        return sprintf(
            '<html><body>
            <p>Dear %s,</p>
            <p>Please find attached invoice <strong>%s</strong> for the amount of <strong>%s %s</strong>.</p>
            <p>Invoice Date: %s<br>Due Date: %s</p>
            <p>Please remit payment by the due date.</p>
            <p>Thank you for your business!</p>
            <p>Best regards,<br>NexSaaS Billing Team</p>
            </body></html>',
            htmlspecialchars($invoice['customer_name']),
            htmlspecialchars($invoice['invoice_no']),
            number_format($invoice['total_amount'], 2),
            htmlspecialchars($invoice['currency_code']),
            htmlspecialchars($invoice['invoice_date']),
            htmlspecialchars($invoice['due_date'])
        );
    }

    /**
     * Post AR journal entry for finalized invoice
     *
     * Requirement: 19.2
     *
     * @param array $invoice
     * @param array $lines
     * @param int $userId
     * @return array{success: bool, data: array|null, error: string|null}
     */
    private function postARJournalEntry(array $invoice, array $lines, int $userId): array
    {
        // Build journal entry
        $entry = [
            'company_code' => $invoice['company_code'],
            'fin_period' => date('Ym', strtotime($invoice['invoice_date'])),
            'voucher_date' => $invoice['invoice_date'],
            'currency_code' => $this->mapCurrencyToCode($invoice['currency_code']),
            'exchange_rate' => $invoice['exchange_rate'],
            'section_code' => '01',  // Income
            'description' => sprintf('AR Invoice %s - %s', $invoice['invoice_no'], $invoice['customer_name']),
            'created_by' => $userId,
            'lines' => []
        ];
        
        // Debit: Accounts Receivable
        $entry['lines'][] = [
            'account_code' => '1200',  // AR account (should be configurable)
            'account_desc' => 'Accounts Receivable',
            'dr_value' => $invoice['total_amount'],
            'cr_value' => 0,
            'line_desc' => sprintf('Invoice %s', $invoice['invoice_no']),
            'customer_invoice_no' => $invoice['invoice_no'],
            'vendor_name' => $invoice['customer_name']
        ];
        
        // Credit: Revenue accounts (per line item)
        foreach ($lines as $line) {
            $revenueAccount = $line['account_code'] ?? '4000';  // Default revenue account
            
            $entry['lines'][] = [
                'account_code' => $revenueAccount,
                'account_desc' => 'Revenue',
                'dr_value' => 0,
                'cr_value' => $line['line_subtotal'] - $line['discount_amount'],
                'line_desc' => $line['description'],
                'customer_invoice_no' => $invoice['invoice_no']
            ];
            
            // Tax line if applicable
            if ($line['tax_amount'] > 0) {
                $entry['lines'][] = [
                    'account_code' => '2300',  // Tax payable account
                    'account_desc' => 'Tax Payable',
                    'dr_value' => 0,
                    'cr_value' => $line['tax_amount'],
                    'line_desc' => sprintf('Tax on %s', $line['description']),
                    'customer_invoice_no' => $invoice['invoice_no']
                ];
            }
        }
        
        // Post journal entry
        return $this->journalService->post($entry);
    }
    
    /**
     * Map ISO currency code to internal currency code (01-06)
     *
     * @param string $isoCurrency
     * @return string
     */
    private function mapCurrencyToCode(string $isoCurrency): string
    {
        $map = [
            'EGP' => '01',
            'USD' => '02',
            'AED' => '03',
            'SAR' => '04',
            'EUR' => '05',
            'GBP' => '06'
        ];
        
        return $map[$isoCurrency] ?? '01';
    }

    /**
     * Get invoice by ID
     *
     * @param int $invoiceId
     * @return array|null
     */
    private function getInvoiceById(int $invoiceId): ?array
    {
        $sql = "SELECT * FROM invoices WHERE id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL";
        
        $db = $this->model->getDb();
        $result = $db->Execute($sql, [
            $invoiceId,
            $this->model->getTenantId(),
            $this->model->getCompanyCode()
        ]);
        
        if (!$result || $result->EOF) {
            return null;
        }
        
        return $result->fields;
    }
    
    /**
     * Get invoice lines
     *
     * @param int $invoiceId
     * @return array
     */
    private function getInvoiceLines(int $invoiceId): array
    {
        $sql = "SELECT * FROM invoice_lines 
                WHERE invoice_id = ? AND tenant_id = ? AND company_code = ? AND deleted_at IS NULL
                ORDER BY line_no";
        
        $db = $this->model->getDb();
        $result = $db->Execute($sql, [
            $invoiceId,
            $this->model->getTenantId(),
            $this->model->getCompanyCode()
        ]);
        
        if (!$result) {
            return [];
        }
        
        $lines = [];
        while (!$result->EOF) {
            $lines[] = $result->fields;
            $result->MoveNext();
        }
        
        return $lines;
    }
    
    /**
     * Get next invoice number with configurable prefix
     *
     * Requirement: 19.2
     *
     * @param string $prefix
     * @return array{invoice_no: string, sequence: int}
     */
    public function getNextInvoiceNumber(string $prefix = 'INV'): array
    {
        $sql = "SELECT COALESCE(MAX(invoice_sequence), 0) + 1 as next_seq 
                FROM invoices 
                WHERE tenant_id = ? AND company_code = ? AND invoice_prefix = ? AND deleted_at IS NULL";
        
        $db = $this->model->getDb();
        $result = $db->Execute($sql, [
            $this->model->getTenantId(),
            $this->model->getCompanyCode(),
            $prefix
        ]);
        
        $nextSeq = 1;
        if ($result && !$result->EOF) {
            $nextSeq = (int)$result->fields['next_seq'];
        }
        
        $invoiceNo = sprintf('%s-%06d', $prefix, $nextSeq);
        
        return [
            'invoice_no' => $invoiceNo,
            'sequence' => $nextSeq
        ];
    }
    
    /**
     * Get next payment number
     *
     * @return string
     */
    private function getNextPaymentNumber(): string
    {
        $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING(payment_no FROM 5) AS INTEGER)), 0) + 1 as next_no 
                FROM payments 
                WHERE tenant_id = ? AND company_code = ? AND payment_no LIKE 'PAY-%' AND deleted_at IS NULL";
        
        $db = $this->model->getDb();
        $result = $db->Execute($sql, [
            $this->model->getTenantId(),
            $this->model->getCompanyCode()
        ]);
        
        $nextNo = 1;
        if ($result && !$result->EOF) {
            $nextNo = (int)$result->fields['next_no'];
        }
        
        return sprintf('PAY-%06d', $nextNo);
    }
    
    /**
     * Insert payment record
     *
     * @param array $data
     * @return int Payment ID
     */
    private function insertPayment(array $data): int
    {
        $data['tenant_id'] = $this->model->getTenantId();
        $data['company_code'] = $this->model->getCompanyCode();
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($data);
        
        $sql = sprintf(
            "INSERT INTO payments (%s) VALUES (%s) RETURNING id",
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $db = $this->model->getDb();
        $result = $db->Execute($sql, $values);
        
        if (!$result || $result->EOF) {
            throw new \RuntimeException('Failed to insert payment');
        }
        
        return (int)$result->fields['id'];
    }
    
    /**
     * Insert payment allocation
     *
     * @param array $data
     * @return void
     */
    private function insertPaymentAllocation(array $data): void
    {
        $data['tenant_id'] = $this->model->getTenantId();
        $data['company_code'] = $this->model->getCompanyCode();
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($data);
        
        $sql = sprintf(
            "INSERT INTO payment_allocations (%s) VALUES (%s)",
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $db = $this->model->getDb();
        $result = $db->Execute($sql, $values);
        
        if ($result === false) {
            throw new \RuntimeException('Failed to insert payment allocation: ' . $db->ErrorMsg());
        }
    }
}
