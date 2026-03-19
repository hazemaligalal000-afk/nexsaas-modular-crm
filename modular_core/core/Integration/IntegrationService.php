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
    }

    public function aiSearchCall($query, $tenantId) {
        $api = new AIInternalClient();
        return $api->post('/search/semantic', [
            'tenant_id' => $tenantId,
            'query' => $query,
            'top_n' => 20
        ]);
    }
}
