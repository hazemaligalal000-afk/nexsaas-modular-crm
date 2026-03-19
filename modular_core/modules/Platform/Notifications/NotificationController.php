<?php

namespace ModularCore\Modules\Platform\Notifications;

use ModularCore\Core\BaseController;

/**
 * Notification Controller
 * 
 * REST API for notification management
 * Requirements: 27.2, 27.4, 27.5
 */
class NotificationController extends BaseController
{
    protected $notificationService;
    
    public function __construct()
    {
        parent::__construct();
        $this->notificationService = new NotificationService();
    }
    
    /**
     * GET /api/v1/platform/notifications
     * List notifications for current user
     */
    public function index()
    {
        $userId = $this->getCurrentUserId();
        $tenantId = $this->getCurrentTenantId();
        
        $unreadOnly = $_GET['unread_only'] ?? false;
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $notifications = $this->notificationService->getUserNotifications(
            $userId,
            $tenantId,
            $unreadOnly,
            $limit,
            $offset
        );
        
        $unreadCount = $this->notificationService->getUnreadCount($userId, $tenantId);
        
        return $this->respond([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'total' => count($notifications)
        ]);
    }
    
    /**
     * PUT /api/v1/platform/notifications/{id}/read
     * Mark notification as read
     */
    public function markRead($id)
    {
        $userId = $this->getCurrentUserId();
        $tenantId = $this->getCurrentTenantId();
        
        $result = $this->notificationService->markAsRead($id, $userId, $tenantId);
        
        if (!$result) {
            return $this->respond(null, 'Notification not found', 404);
        }
        
        return $this->respond(['success' => true]);
    }
    
    /**
     * PUT /api/v1/platform/notifications/mark-all-read
     * Mark all notifications as read
     */
    public function markAllRead()
    {
        $userId = $this->getCurrentUserId();
        $tenantId = $this->getCurrentTenantId();
        
        $count = $this->notificationService->markAllAsRead($userId, $tenantId);
        
        return $this->respond([
            'success' => true,
            'marked_count' => $count
        ]);
    }
    
    /**
     * DELETE /api/v1/platform/notifications/{id}
     * Delete notification
     */
    public function delete($id)
    {
        $userId = $this->getCurrentUserId();
        $tenantId = $this->getCurrentTenantId();
        
        $result = $this->notificationService->delete($id, $userId, $tenantId);
        
        if (!$result) {
            return $this->respond(null, 'Notification not found', 404);
        }
        
        return $this->respond(['success' => true]);
    }
    
    /**
     * GET /api/v1/platform/notifications/unread-count
     * Get unread notification count
     */
    public function unreadCount()
    {
        $userId = $this->getCurrentUserId();
        $tenantId = $this->getCurrentTenantId();
        
        $count = $this->notificationService->getUnreadCount($userId, $tenantId);
        
        return $this->respond(['unread_count' => $count]);
    }
}
