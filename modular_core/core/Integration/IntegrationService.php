<?php
namespace Core\Integration;

use Core\Events\EventBus;
use Core\Queue\CeleryClient;

/**
 * IntegrationService: Wires all event bus and Celery tasks per Phase 7.
 * Task 63.1, 63.2, 63.3
 */
class IntegrationService {
    public function wireAll() {
        // Wire RabbitMQ event bus integrations
        $bus = EventBus::getInstance();

        $bus->subscribe('lead.captured', 'LeadScoringWorker');
        $bus->subscribe('lead.score_request', 'AIScoringWorker');
        $bus->subscribe('deal.win_probability_request', 'AIWinProbabilityWorker');
        $bus->subscribe('inbox.message.received', 'SentimentAnalysisWorker');
        $bus->subscribe('workflow.execute', 'WorkflowExecutor');
        $bus->subscribe('webhook.deliver', 'WebhookDeliverer');
        $bus->subscribe('email.sync', 'EmailSyncEngine');
        $bus->subscribe('payroll.run', 'PayrollLedgerPoster');
        $bus->subscribe('fx.revalue', 'FXRevaluationRunner');
        $bus->subscribe('depreciation.run', 'DepreciationCalculations');
        $bus->subscribe('embedding.update', 'AIEmbeddingGenerator');

        // Wire Celery scheduled tasks
        $celery = CeleryClient::getInstance();

        $celery->scheduleDaily('DealRottingCheck');
        $celery->scheduleDaily('OverdueInvoicesCheck');
        $celery->scheduleHourly('FXRateRefresh');
        $celery->scheduleMonthly('AssetDepreciationTask');
        $celery->scheduleMonthly('PayrollFinalization');
        $celery->scheduleNightly('ChurnPredictionRecompute');
        $celery->scheduleWeekly('WIPStaleCheck');
        
        // Batch D: AR/AP Schedule
        $celery->scheduleDaily('AccountsReceivableOverdueAlerts');
        $celery->scheduleHourly('ETAEInvoiceSubmissionSync');
        
        // Batch E: Bank Management Schedule
        $celery->scheduleDaily('BankFeedAutoReconciliation');
        $celery->scheduleMonthly('CashFlowForecastRefresh');
        
        // Batch J: Financial Statements
        $celery->scheduleMonthly('EndOfPeriodClosingSnapshot');
    }

    public function aiSearchCall($query, $tenantId) {
        $api = new AIInternalClient();
        return $api->post('/search/semantic', [
            'tenant_id' => $tenantId,
            'query' => $query,
            'top_n' => 20
        ]);
    }

    /**
     * Batch M: AI Anomaly Detection Wrapper
     */
    public function detectAccountingAnomaly(string $tenantId, string $companyCode, string $accountCode, float $amount, string $debitCredit) {
        try {
            $api = new AIInternalClient();
            return $api->post('/accounting/outlier-detect', [
                'tenant_id' => $tenantId,
                'company_code' => $companyCode,
                'account_code' => $accountCode,
                'amount' => $amount,
                'debit_credit' => $debitCredit
            ]);
        } catch (\Exception $e) {
            // Dead-letter queue fallback requirement: return null/safe defaults if AI down
            \Core\AuditLogger::log($tenantId, 'SYSTEM', 'AI_ENGINE_DOWN', 'ERROR', $e->getMessage(), 0, []);
            return ['is_outlier' => false, 'confidence' => 0];
        }
    }

    /**
     * Batch M: Duplicate Voucher Detection (Req 57.2)
     */
    public function checkDuplicateVoucher(string $tenantId, string $companyCode, string $vendorCode, float $amount, string $date) {
        try {
            $api = new AIInternalClient();
            return $api->post('/accounting/duplicate-check', [
                'tenant_id' => $tenantId,
                'company_code' => $companyCode,
                'vendor_code' => $vendorCode,
                'amount' => $amount,
                'date' => $date
            ]);
        } catch (\Exception $e) {
            return ['is_duplicate' => false, 'confidence' => 0];
        }
    }
}
