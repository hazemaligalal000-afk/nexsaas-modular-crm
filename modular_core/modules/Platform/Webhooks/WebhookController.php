<?php

namespace ModularCore\Modules\Platform\Webhooks;

use ModularCore\Core\BaseController;

/**
 * Webhook Controller
 * 
 * REST API for webhook management
 * Requirements: 31.1, 31.2, 31.3
 */
class WebhookController extends BaseController
{
    protected $webhookService;
    
    public function __construct()
    {
        parent::__construct();
        $this->webhookService = new WebhookService();
        
        // Require webhook management permission
        $this->requirePermission('webhooks.manage');
    }
    
    /**
     * GET /api/v1/platform/webhooks
     * List all webhooks
     */
    public function index()
    {
        $webhooks = $this->webhookService->list();
        
        return $this->respond(['webhooks' => $webhooks]);
    }
    
    /**
     * POST /api/v1/platform/webhooks
     * Register new webhook
     */
    public function create()
    {
        $data = $this->getRequestBody();
        
        if (empty($data['name']) || empty($data['url']) || empty($data['events'])) {
            return $this->respond(null, 'Missing required fields: name, url, events', 400);
        }
        
        // Validate URL
        if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            return $this->respond(null, 'Invalid URL format', 400);
        }
        
        // Validate events array
        if (!is_array($data['events']) || empty($data['events'])) {
            return $this->respond(null, 'Events must be a non-empty array', 400);
        }
        
        $result = $this->webhookService->register(
            $data['name'],
            $data['url'],
            $data['events']
        );
        
        if ($result['success']) {
            return $this->respond($result, null, 201);
        }
        
        return $this->respond(null, $result['error'], 500);
    }
    
    /**
     * PUT /api/v1/platform/webhooks/{id}
     * Update webhook
     */
    public function update($id)
    {
        $data = $this->getRequestBody();
        
        $result = $this->webhookService->update((int)$id, $data);
        
        if ($result) {
            return $this->respond(['success' => true]);
        }
        
        return $this->respond(null, 'Failed to update webhook', 500);
    }
    
    /**
     * DELETE /api/v1/platform/webhooks/{id}
     * Delete webhook
     */
    public function delete($id)
    {
        $result = $this->webhookService->delete((int)$id);
        
        if ($result) {
            return $this->respond(['success' => true]);
        }
        
        return $this->respond(null, 'Failed to delete webhook', 500);
    }
    
    /**
     * GET /api/v1/platform/webhooks/{id}/deliveries
     * Get delivery history
     */
    public function deliveries($id)
    {
        $limit = (int)($_GET['limit'] ?? 100);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $deliveries = $this->webhookService->getDeliveries((int)$id, $limit, $offset);
        
        return $this->respond(['deliveries' => $deliveries]);
    }
}
