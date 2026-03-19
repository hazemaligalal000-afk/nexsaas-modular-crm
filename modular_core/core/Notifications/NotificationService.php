<?php
namespace Core\Notifications;

use Core\BaseService;
use Core\Database;

/**
 * NotificationService: WebSocket and push notifications.
 * Task 43.1, 43.2, 43.3
 * Requirements: 27.1, 27.2, 27.3, 27.4, 27.5
 */
class NotificationService extends BaseService {
    
    /**
     * Send notification to user
     * Push within 3s via WebSocket
     */
    public function send($userId, $type, $content, $metadata = []) {
        return $this->transaction(function() use ($userId, $type, $content, $metadata) {
            $db = Database::getInstance();

            // Persistence
            $sql = "INSERT INTO notifications (
                tenant_id, user_id, type, content, metadata, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'unread', NOW()) RETURNING id";
            $res = $db->query($sql, [
                $this->tenantId, 
                $userId, 
                $type, 
                $content,
                json_encode($metadata)
            ]);
            $notificationId = $res[0]['id'];

            // Push to Redis for WebSocket
            $redis = \Core\Performance\CacheManager::getInstance();
            $msg = json_encode([
                'id' => $notificationId,
                'type' => $type,
                'content' => $content,
                'metadata' => $metadata,
                'timestamp' => date('c')
            ]);
            
            // Store in pending list if user offline
            $pendingKey = "notifications:pending:{$userId}";
            $redis->rPush($pendingKey, $msg);
            $redis->expire($pendingKey, 86400 * 7); // 7 days TTL
            
            // Publish to WebSocket channel
            $channel = "tenant:{$this->tenantId}:user:{$userId}";
            $redis->publish($channel, $msg);

            return ['success' => true, 'id' => $notificationId];
        });
    }

    /**
     * Get user notifications with pagination
     */
    public function getUserNotifications($userId, $tenantId, $unreadOnly = false, $limit = 50, $offset = 0) {
        $db = Database::getInstance();
        
        $whereClause = "user_id = ? AND tenant_id = ? AND deleted_at IS NULL";
        $params = [$userId, $tenantId];
        
        if ($unreadOnly) {
            $whereClause .= " AND read_at IS NULL";
        }
        
        $sql = "SELECT * FROM notifications 
                WHERE {$whereClause}
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $db->query($sql, $params);
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($userId, $tenantId) {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as count FROM notifications 
                WHERE user_id = ? AND tenant_id = ? AND read_at IS NULL AND deleted_at IS NULL";
        $result = $db->query($sql, [$userId, $tenantId]);
        return (int)$result[0]['count'];
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId, $tenantId) {
        $db = Database::getInstance();
        $sql = "UPDATE notifications SET read_at = NOW(), status = 'read' 
                WHERE id = ? AND user_id = ? AND tenant_id = ?";
        $result = $db->query($sql, [$notificationId, $userId, $tenantId]);
        return $result !== false;
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead($userId, $tenantId) {
        $db = Database::getInstance();
        $sql = "UPDATE notifications SET read_at = NOW(), status = 'read' 
                WHERE user_id = ? AND tenant_id = ? AND read_at IS NULL";
        $result = $db->query($sql, [$userId, $tenantId]);
        return $db->affectedRows();
    }
    
    /**
     * Delete notification (soft delete)
     */
    public function delete($notificationId, $userId, $tenantId) {
        $db = Database::getInstance();
        $sql = "UPDATE notifications SET deleted_at = NOW() 
                WHERE id = ? AND user_id = ? AND tenant_id = ?";
        $result = $db->query($sql, [$notificationId, $userId, $tenantId]);
        return $result !== false;
    }
    
    /**
     * Cleanup old notifications (90-day TTL)
     * Celery task
     */
    public function cleanupOldNotifications() {
        $db = Database::getInstance();
        $sql = "DELETE FROM notifications WHERE created_at < NOW() - INTERVAL '90 days'";
        $db->query($sql);
        return ['success' => true];
    }
}
