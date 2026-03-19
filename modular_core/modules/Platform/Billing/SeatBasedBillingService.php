<?php

namespace NexSaaS\Platform\Billing;

use Stripe\StripeClient;

/**
 * Seat-Based Billing Service
 * Track users and calculate seat-based charges
 * Requirements: Master Spec - Seat-Based Billing
 */
class SeatBasedBillingService
{
    private $stripe;
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
        $this->stripe = new StripeClient(getenv('STRIPE_SECRET_KEY'));
    }
    
    /**
     * Get active seat count for tenant
     */
    public function getActiveSeatCount(int $tenantId): int
    {
        $query = "
            SELECT COUNT(*) as count
            FROM users
            WHERE tenant_id = ?
            AND status = 'active'
            AND deleted_at IS NULL
        ";
        
        $result = $this->db->queryOne($query, [$tenantId]);
        return (int)$result['count'];
    }
    
    /**
     * Update seat count in Stripe
     */
    public function updateSeatCount(int $tenantId): array
    {
        $seatCount = $this->getActiveSeatCount($tenantId);
        $subscription = $this->getTenantSubscription($tenantId);
        
        if (!$subscription) {
            throw new \Exception('No active subscription found');
        }
        
        // Update subscription quantity
        $this->stripe->subscriptionItems->update(
            $subscription['stripe_subscription_item_id'],
            ['quantity' => $seatCount]
        );
        
        // Record seat change
        $this->db->insert('seat_changes', [
            'tenant_id' => $tenantId,
            'previous_seats' => $subscription['seat_count'],
            'new_seats' => $seatCount,
            'changed_at' => date('Y-m-d H:i:s')
        ]);
        
        // Update local record
        $this->db->update('tenant_subscriptions', [
            'seat_count' => $seatCount,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['tenant_id' => $tenantId]);
        
        return [
            'tenant_id' => $tenantId,
            'seat_count' => $seatCount,
            'previous_count' => $subscription['seat_count']
        ];
    }
    
    /**
     * Check if tenant can add more users
     */
    public function canAddUser(int $tenantId): bool
    {
        $currentSeats = $this->getActiveSeatCount($tenantId);
        $subscription = $this->getTenantSubscription($tenantId);
        
        // If no seat limit, allow
        if (!isset($subscription['max_seats']) || $subscription['max_seats'] === null) {
            return true;
        }
        
        return $currentSeats < $subscription['max_seats'];
    }
    
    /**
     * Add user and update billing
     */
    public function addUser(int $tenantId, array $userData): array
    {
        if (!$this->canAddUser($tenantId)) {
            throw new \Exception('Seat limit reached. Please upgrade your plan.');
        }
        
        // Add user
        $userId = $this->db->insert('users', array_merge($userData, [
            'tenant_id' => $tenantId,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]));
        
        // Update seat count
        $this->updateSeatCount($tenantId);
        
        return [
            'user_id' => $userId,
            'seat_count' => $this->getActiveSeatCount($tenantId)
        ];
    }
    
    /**
     * Remove user and update billing
     */
    public function removeUser(int $tenantId, int $userId): array
    {
        // Soft delete user
        $this->db->update('users', [
            'status' => 'inactive',
            'deleted_at' => date('Y-m-d H:i:s')
        ], ['id' => $userId, 'tenant_id' => $tenantId]);
        
        // Update seat count
        $this->updateSeatCount($tenantId);
        
        return [
            'user_id' => $userId,
            'seat_count' => $this->getActiveSeatCount($tenantId)
        ];
    }
    
    /**
     * Calculate prorated charge for seat change
     */
    public function calculateProration(int $tenantId, int $newSeatCount): array
    {
        $subscription = $this->getTenantSubscription($tenantId);
        $currentSeats = $subscription['seat_count'];
        $pricePerSeat = $subscription['price_per_seat'];
        
        // Calculate days remaining in billing period
        $periodEnd = strtotime($subscription['current_period_end']);
        $now = time();
        $daysRemaining = max(0, ceil(($periodEnd - $now) / 86400));
        $totalDays = 30; // Assume 30-day billing cycle
        
        // Calculate prorated amount
        $seatDifference = $newSeatCount - $currentSeats;
        $proratedAmount = ($seatDifference * $pricePerSeat * $daysRemaining) / $totalDays;
        
        return [
            'current_seats' => $currentSeats,
            'new_seats' => $newSeatCount,
            'seat_difference' => $seatDifference,
            'price_per_seat' => $pricePerSeat,
            'days_remaining' => $daysRemaining,
            'prorated_amount' => round($proratedAmount, 2),
            'will_be_charged_now' => $proratedAmount > 0
        ];
    }
    
    /**
     * Get seat usage history
     */
    public function getSeatHistory(int $tenantId, int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $query = "
            SELECT *
            FROM seat_changes
            WHERE tenant_id = ?
            AND changed_at >= ?
            ORDER BY changed_at DESC
        ";
        
        $changes = $this->db->query($query, [$tenantId, $startDate]);
        
        return [
            'tenant_id' => $tenantId,
            'current_seats' => $this->getActiveSeatCount($tenantId),
            'changes' => $changes
        ];
    }
    
    private function getTenantSubscription(int $tenantId): ?array
    {
        $query = "
            SELECT *
            FROM tenant_subscriptions
            WHERE tenant_id = ?
            AND status = 'active'
        ";
        
        return $this->db->queryOne($query, [$tenantId]);
    }
}
