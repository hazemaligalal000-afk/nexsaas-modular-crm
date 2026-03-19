<?php

namespace ModularCore\Modules\Platform\Webhooks;

use ModularCore\Core\BaseService;

/**
 * Webhook Service
 * 
 * Manage webhook subscriptions and deliveries
 * Requirements: 31.1, 31.2, 31.3, 31.4, 31.5
 */
class WebhookService extends BaseService
{
    /**
     * Register a new webhook
     */
    public function register(string $name, string $url, array $events): array
    {
        global $db;
        
        $tenantId = $this->getCurrentTenantId();
        $companyCode = $this->getCurrentCompanyCode();
        $userId = $this->getCurrentUserId();
        
        // Generate secret for HMAC signing
        $secret = bin2hex(random_bytes(32));
        
        $sql = "INSERT INTO webhooks (
            tenant_id, company_code, name, url, events, secret, is_active, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, true, ?, NOW()) RETURNING id";
        
        $result = $db->Execute($sql, [
            $tenantId,
            $companyCode,
            $name,
            $url,
            '{' . implode(',', $events) . '}', // PostgreSQL array format
            $secret,
            $userId
        ]);
        
        if ($result && !$result->EOF) {
            return [
                'id' => $result->fields['id'],
                'secret' => $secret,
                'success' => true
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to register webhook'];
    }
    
    /**
     * Get all webhooks for tenant
     */
    public function list(): array
    {
        global $db;
        
        $tenantId = $this->getCurrentTenantId();
        
        $sql = "SELECT id, name, url, events, is_active, created_at, updated_at 
                FROM webhooks 
                WHERE tenant_id = ? AND deleted_at IS NULL
                ORDER BY created_at DESC";
        
        $result = $db->Execute($sql, [$tenantId]);
        
        $webhooks = [];
        while ($result && !$result->EOF) {
            $webhooks[] = $result->fields;
            $result->MoveNext();
        }
        
        return $webhooks;
    }
    
    /**
     * Update webhook
     */
    public function update(int $webhookId, array $data): bool
    {
        global $db;
        
        $tenantId = $this->getCurrentTenantId();
        
        $updates = [];
        $params = [];
        
        if (isset($data['name'])) {
            $updates[] = "name = ?";
            $params[] = $data['name'];
        }
        
        if (isset($data['url'])) {
            $updates[] = "url = ?";
            $params[] = $data['url'];
        }
        
        if (isset($data['events'])) {
            $updates[] = "events = ?";
            $params[] = '{' . implode(',', $data['events']) . '}';
        }
        
        if (isset($data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = $data['is_active'];
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $webhookId;
        $params[] = $tenantId;
        
        $sql = "UPDATE webhooks SET " . implode(', ', $updates) . 
               " WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL";
        
        $db->Execute($sql, $params);
        
        return true;
    }
    
    /**
     * Delete webhook (soft delete)
     */
    public function delete(int $webhookId): bool
    {
        global $db;
        
        $tenantId = $this->getCurrentTenantId();
        
        $sql = "UPDATE webhooks SET deleted_at = NOW() 
                WHERE id = ? AND tenant_id = ?";
        
        $db->Execute($sql, [$webhookId, $tenantId]);
        
        return true;
    }
    
    /**
     * Trigger webhook delivery
     * Enqueue for async processing
     */
    public function trigger(string $eventType, array $payload): void
    {
        global $db;
        
        $tenantId = $this->getCurrentTenantId();
        
        // Find all active webhooks subscribed to this event
        $sql = "SELECT id, url, secret FROM webhooks 
                WHERE tenant_id = ? AND is_active = true AND deleted_at IS NULL 
                AND ? = ANY(events)";
        
        $result = $db->Execute($sql, [$tenantId, $eventType]);
        
        while ($result && !$result->EOF) {
            $webhookId = $result->fields['id'];
            
            // Create delivery record
            $deliverySql = "INSERT INTO webhook_deliveries (
                webhook_id, tenant_id, event_type, payload, status, created_at
            ) VALUES (?, ?, ?, ?, 'pending', NOW()) RETURNING id";
            
            $deliveryResult = $db->Execute($deliverySql, [
                $webhookId,
                $tenantId,
                $eventType,
                json_encode($payload)
            ]);
            
            if ($deliveryResult && !$deliveryResult->EOF) {
                $deliveryId = $deliveryResult->fields['id'];
                
                // Enqueue for async delivery via RabbitMQ
                $this->enqueueDelivery($deliveryId, $webhookId, $result->fields['url'], 
                                      $result->fields['secret'], $eventType, $payload);
            }
            
            $result->MoveNext();
        }
    }
    
    /**
     * Enqueue webhook delivery to RabbitMQ
     */
    protected function enqueueDelivery(int $deliveryId, int $webhookId, string $url, 
                                      string $secret, string $eventType, array $payload): void
    {
        // Use RabbitMQ to enqueue delivery
        $redis = new \Redis();
        $redis->connect('redis', 6379);
        
        $message = json_encode([
            'delivery_id' => $deliveryId,
            'webhook_id' => $webhookId,
            'url' => $url,
            'secret' => $secret,
            'event_type' => $eventType,
            'payload' => $payload,
            'attempt' => 1
        ]);
        
        $redis->rPush('webhook:deliveries', $message);
    }
    
    /**
     * Deliver webhook via HTTP POST
     * Called by Celery worker
     */
    public function deliver(int $deliveryId, int $webhookId, string $url, string $secret, 
                           string $eventType, array $payload, int $attempt = 1): array
    {
        global $db;
        
        // Generate HMAC-SHA256 signature
        $payloadJson = json_encode($payload);
        $signature = hash_hmac('sha256', $payloadJson, $secret);
        
        // Send HTTP POST request
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Webhook-Signature: ' . $signature,
            'X-Webhook-Event: ' . $eventType,
            'X-Webhook-Delivery: ' . $deliveryId
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Update delivery record
        if ($httpCode >= 200 && $httpCode < 300) {
            // Success
            $sql = "UPDATE webhook_deliveries SET 
                    status = 'success', 
                    http_status_code = ?, 
                    response_body = ?, 
                    delivered_at = NOW(),
                    attempt_number = ?
                    WHERE id = ?";
            
            $db->Execute($sql, [$httpCode, substr($response, 0, 1000), $attempt, $deliveryId]);
            
            return ['success' => true, 'http_code' => $httpCode];
        } else {
            // Failed - retry with exponential backoff
            $sql = "UPDATE webhook_deliveries SET 
                    status = 'failed', 
                    http_status_code = ?, 
                    response_body = ?, 
                    error_message = ?,
                    attempt_number = ?
                    WHERE id = ?";
            
            $db->Execute($sql, [
                $httpCode, 
                substr($response, 0, 1000), 
                $error ?: 'HTTP ' . $httpCode,
                $attempt,
                $deliveryId
            ]);
            
            // Retry up to 5 times with exponential backoff
            if ($attempt < 5) {
                $this->scheduleRetry($deliveryId, $webhookId, $url, $secret, 
                                    $eventType, $payload, $attempt + 1);
            }
            
            return ['success' => false, 'http_code' => $httpCode, 'error' => $error];
        }
    }
    
    /**
     * Schedule retry with exponential backoff
     * Delays: 1min, 5min, 30min, 2h, 12h
     */
    protected function scheduleRetry(int $deliveryId, int $webhookId, string $url, 
                                    string $secret, string $eventType, array $payload, int $attempt): void
    {
        $delays = [60, 300, 1800, 7200, 43200]; // seconds
        $delay = $delays[$attempt - 1] ?? 43200;
        
        // Schedule via Redis with delay
        $redis = new \Redis();
        $redis->connect('redis', 6379);
        
        $message = json_encode([
            'delivery_id' => $deliveryId,
            'webhook_id' => $webhookId,
            'url' => $url,
            'secret' => $secret,
            'event_type' => $eventType,
            'payload' => $payload,
            'attempt' => $attempt,
            'scheduled_at' => time() + $delay
        ]);
        
        $redis->rPush('webhook:retries', $message);
    }
    
    /**
     * Get delivery history for webhook
     */
    public function getDeliveries(int $webhookId, int $limit = 100, int $offset = 0): array
    {
        global $db;
        
        $tenantId = $this->getCurrentTenantId();
        
        $sql = "SELECT wd.* FROM webhook_deliveries wd
                JOIN webhooks w ON w.id = wd.webhook_id
                WHERE wd.webhook_id = ? AND w.tenant_id = ?
                ORDER BY wd.created_at DESC
                LIMIT ? OFFSET ?";
        
        $result = $db->Execute($sql, [$webhookId, $tenantId, $limit, $offset]);
        
        $deliveries = [];
        while ($result && !$result->EOF) {
            $delivery = $result->fields;
            $delivery['payload'] = json_decode($delivery['payload'], true);
            $deliveries[] = $delivery;
            $result->MoveNext();
        }
        
        return $deliveries;
    }
    
    /**
     * Cleanup old deliveries (30-day retention)
     * Celery task
     */
    public function cleanupOldDeliveries(): int
    {
        global $db;
        
        $sql = "DELETE FROM webhook_deliveries WHERE created_at < NOW() - INTERVAL '30 days'";
        $db->Execute($sql);
        
        return $db->Affected_Rows();
    }
}
