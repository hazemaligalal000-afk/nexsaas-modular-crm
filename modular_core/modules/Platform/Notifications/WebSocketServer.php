<?php

namespace ModularCore\Modules\Platform\Notifications;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use ModularCore\Core\BaseService;

/**
 * WebSocket Notification Server
 * 
 * Implements real-time push notifications via WebSocket
 * Requirements: 27.1, 27.3
 */
class WebSocketServer implements MessageComponentInterface
{
    protected $clients;
    protected $userConnections;
    protected $redis;
    
    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        $this->redis = new \Redis();
        $this->redis->connect('redis', 6379);
    }
    
    /**
     * Handle new connection
     * Authenticate and subscribe to user channel: tenant:{tenant_id}:user:{user_id}
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        
        // Parse query string for auth token
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $params);
        
        if (!isset($params['token'])) {
            $conn->close();
            return;
        }
        
        // Validate JWT token and extract user info
        $userData = $this->validateToken($params['token']);
        if (!$userData) {
            $conn->close();
            return;
        }
        
        $conn->userId = $userData['user_id'];
        $conn->tenantId = $userData['tenant_id'];
        
        // Store connection by user
        $userKey = "{$userData['tenant_id']}:{$userData['user_id']}";
        $this->userConnections[$userKey] = $conn;
        
        // Flush pending notifications from Redis
        $this->flushPendingNotifications($conn);
        
        echo "New connection: User {$conn->userId} from tenant {$conn->tenantId}\n";
    }
    
    /**
     * Handle incoming message (heartbeat, mark read, etc.)
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            return;
        }
        
        switch ($data['type']) {
            case 'ping':
                $from->send(json_encode(['type' => 'pong']));
                break;
                
            case 'mark_read':
                if (isset($data['notification_id'])) {
                    $this->markNotificationRead($from->userId, $data['notification_id']);
                }
                break;
                
            case 'mark_all_read':
                $this->markAllNotificationsRead($from->userId, $from->tenantId);
                break;
        }
    }
    
    /**
     * Handle connection close
     */
    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        
        if (isset($conn->userId) && isset($conn->tenantId)) {
            $userKey = "{$conn->tenantId}:{$conn->userId}";
            unset($this->userConnections[$userKey]);
            echo "Connection closed: User {$conn->userId}\n";
        }
    }
    
    /**
     * Handle connection error
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
    
    /**
     * Send notification to specific user
     * If user offline, store in Redis pending list
     */
    public function sendToUser(string $tenantId, string $userId, array $notification)
    {
        $userKey = "{$tenantId}:{$userId}";
        
        if (isset($this->userConnections[$userKey])) {
            // User is online, send immediately
            $conn = $this->userConnections[$userKey];
            $conn->send(json_encode($notification));
        } else {
            // User is offline, store in Redis
            $pendingKey = "notifications:pending:{$userId}";
            $this->redis->rPush($pendingKey, json_encode($notification));
            $this->redis->expire($pendingKey, 86400 * 7); // 7 days TTL
        }
    }
    
    /**
     * Flush pending notifications on reconnect
     */
    protected function flushPendingNotifications(ConnectionInterface $conn)
    {
        $pendingKey = "notifications:pending:{$conn->userId}";
        
        while ($notification = $this->redis->lPop($pendingKey)) {
            $conn->send($notification);
        }
    }
    
    /**
     * Validate JWT token and extract user data
     */
    protected function validateToken(string $token): ?array
    {
        try {
            // Use JWT validation from Auth module
            $authService = new \ModularCore\Modules\Platform\Auth\AuthService();
            return $authService->validateToken($token);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Mark notification as read
     */
    protected function markNotificationRead(string $userId, int $notificationId)
    {
        global $db;
        $db->Execute(
            "UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?",
            [$notificationId, $userId]
        );
    }
    
    /**
     * Mark all notifications as read for user
     */
    protected function markAllNotificationsRead(string $userId, string $tenantId)
    {
        global $db;
        $db->Execute(
            "UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND tenant_id = ? AND read_at IS NULL",
            [$userId, $tenantId]
        );
    }
}
