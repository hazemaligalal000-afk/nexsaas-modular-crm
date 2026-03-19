<?php

namespace NexSaaS\Platform\Billing;

/**
 * Dunning Service
 * Failed payment recovery with email sequence
 * Requirements: Master Spec - Payment Recovery
 */
class DunningService
{
    private $db;
    private $maxAttempts = 3;
    private $attemptIntervals = [1, 3, 7]; // days between attempts
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    /**
     * Handle failed payment
     */
    public function handleFailedPayment(int $tenantId, $invoice): void
    {
        // Get current dunning status
        $dunning = $this->getCurrentDunning($tenantId);
        
        if (!$dunning) {
            // Start new dunning sequence
            $this->startDunningSequence($tenantId, $invoice);
        } else {
            // Increment attempt
            $this->incrementAttempt($tenantId, $invoice);
        }
    }
    
    /**
     * Start dunning sequence
     */
    private function startDunningSequence(int $tenantId, $invoice): void
    {
        $this->db->insert('dunning_sequences', [
            'tenant_id' => $tenantId,
            'invoice_id' => $invoice->id,
            'amount_due' => $invoice->amount_due / 100,
            'attempt_count' => 1,
            'status' => 'active',
            'started_at' => date('Y-m-d H:i:s'),
            'next_attempt_at' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);
        
        // Send first dunning email
        $this->sendDunningEmail($tenantId, 1, $invoice);
    }
    
    /**
     * Increment dunning attempt
     */
    private function incrementAttempt(int $tenantId, $invoice): void
    {
        $dunning = $this->getCurrentDunning($tenantId);
        $newAttempt = $dunning['attempt_count'] + 1;
        
        if ($newAttempt > $this->maxAttempts) {
            // Max attempts reached - suspend account
            $this->suspendAccount($tenantId);
            return;
        }
        
        $nextInterval = $this->attemptIntervals[$newAttempt - 1] ?? 7;
        
        $this->db->update('dunning_sequences', [
            'attempt_count' => $newAttempt,
            'last_attempt_at' => date('Y-m-d H:i:s'),
            'next_attempt_at' => date('Y-m-d H:i:s', strtotime("+{$nextInterval} days"))
        ], ['id' => $dunning['id']]);
        
        // Send dunning email
        $this->sendDunningEmail($tenantId, $newAttempt, $invoice);
    }
    
    /**
     * Send dunning email
     */
    private function sendDunningEmail(int $tenantId, int $attemptNumber, $invoice): void
    {
        $tenant = $this->getTenantInfo($tenantId);
        $amountDue = $invoice->amount_due / 100;
        
        $subjects = [
            1 => "Payment Failed - Action Required",
            2 => "Second Notice: Payment Failed",
            3 => "Final Notice: Account Suspension Pending"
        ];
        
        $bodies = [
            1 => "We were unable to process your payment of \${$amountDue}. Please update your payment method to continue using NexSaaS.",
            2 => "This is your second notice. Your payment of \${$amountDue} has failed. Please update your payment method within 3 days.",
            3 => "FINAL NOTICE: Your account will be suspended in 7 days if payment is not received. Amount due: \${$amountDue}"
        ];
        
        $subject = $subjects[$attemptNumber];
        $body = "Hi {$tenant['company_name']},\n\n{$bodies[$attemptNumber]}\n\nUpdate Payment Method: https://app.nexsaas.com/billing\n\nBest regards,\nNexSaaS Team";
        
        mail($tenant['email'], $subject, $body);
    }
    
    /**
     * Reset dunning attempts after successful payment
     */
    public function resetAttempts(int $tenantId): void
    {
        $this->db->update('dunning_sequences', [
            'status' => 'resolved',
            'resolved_at' => date('Y-m-d H:i:s')
        ], ['tenant_id' => $tenantId, 'status' => 'active']);
    }
    
    /**
     * Suspend account
     */
    private function suspendAccount(int $tenantId): void
    {
        $this->db->update('tenants', [
            'status' => 'suspended',
            'suspended_at' => date('Y-m-d H:i:s'),
            'suspension_reason' => 'payment_failure'
        ], ['id' => $tenantId]);
        
        $this->db->update('dunning_sequences', [
            'status' => 'suspended',
            'suspended_at' => date('Y-m-d H:i:s')
        ], ['tenant_id' => $tenantId, 'status' => 'active']);
        
        // Send suspension email
        $this->sendSuspensionEmail($tenantId);
    }
    
    /**
     * Send suspension email
     */
    private function sendSuspensionEmail(int $tenantId): void
    {
        $tenant = $this->getTenantInfo($tenantId);
        
        $subject = "Account Suspended - Payment Required";
        $body = "Hi {$tenant['company_name']},\n\nYour NexSaaS account has been suspended due to failed payment.\n\nTo reactivate your account, please update your payment method and contact support.\n\nUpdate Payment: https://app.nexsaas.com/billing\nContact Support: support@nexsaas.com\n\nBest regards,\nNexSaaS Team";
        
        mail($tenant['email'], $subject, $body);
    }
    
    private function getCurrentDunning(int $tenantId): ?array
    {
        $query = "SELECT * FROM dunning_sequences WHERE tenant_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1";
        return $this->db->queryOne($query, [$tenantId]);
    }
    
    private function getTenantInfo(int $tenantId): array
    {
        $query = "SELECT email, company_name FROM tenants WHERE id = ?";
        return $this->db->queryOne($query, [$tenantId]);
    }
}
