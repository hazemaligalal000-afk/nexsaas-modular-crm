<?php

namespace ModularCore\Modules\CustomerPortal;

/**
 * Customer Portal Auth Controller (F3 Roadmap)
 * 
 * Provides isolated authentication for business clients, 
 * independent of the internal CRM team users.
 */
class PortalAuthController
{
    private $db;
    private $session;

    public function __construct($db, $session)
    {
        $this->db = $db;
        $this->session = $session;
    }

    /**
     * Authenticate a Client User
     */
    public function login($email, $password, $tenantId)
    {
        $client = $this->db->queryOne("
            SELECT * FROM portal_users 
            WHERE email = :email AND tenant_id = :tid AND active = 1
        ", ['email' => $email, 'tid' => $tenantId]);

        if ($client && password_verify($password, $client['password'])) {
            $token = bin2hex(random_bytes(32));
            $this->session->set('portal_user', $client['id']);
            $this->session->set('portal_tenant', $tenantId);
            $this->session->set('portal_token', $token);

            $this->db->execute("UPDATE portal_users SET last_login = NOW() WHERE id = :id", ['id' => $client['id']]);
            return ['success' => true, 'token' => $token, 'client' => $this->sanitize($client)];
        }

        return ['success' => false, 'error' => 'Invalid credentials or inactive account'];
    }

    /**
     * Validate Portal Session
     */
    public function checkSession()
    {
        $userId = $this->session->get('portal_user');
        if (!$userId) return false;

        $client = $this->db->queryOne("SELECT * FROM portal_users WHERE id = :id", ['id' => $userId]);
        return $client ? $this->sanitize($client) : false;
    }

    private function sanitize($client)
    {
        unset($client['password']);
        return $client;
    }
}
