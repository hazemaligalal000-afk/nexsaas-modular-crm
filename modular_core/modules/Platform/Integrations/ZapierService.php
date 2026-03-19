<?php
namespace Modules\Platform\Integrations;

use Core\BaseService;

/**
 * Zapier Service: Webhook & Trigger management for NexSaaS.
 * (Phase 10: Advanced Features Roadmap)
 */
class ZapierService extends BaseService {
    
    public function onLeadCaptured(string $tenantId, array $leadData) {
        $webhook = $this->db->getOne("SELECT zap_webhook_url FROM zap_configs WHERE tenant_id = ? AND event = 'lead_captured'", [$tenantId]);
        if ($webhook) {
            $this->deliver($webhook, $leadData);
        }
    }

    private function deliver($url, $data) {
        // Asynchronous delivery via CeleryClient (Phases 7/10)
        $celery = \Core\Queue\CeleryClient::getInstance();
        $celery->dispatch('webhook.deliver', ['url' => $url, 'payload' => $data]);
    }
}
