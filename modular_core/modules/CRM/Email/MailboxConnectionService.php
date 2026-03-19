<?php
/**
 * CRM/Email/MailboxConnectionService.php
 *
 * Manages per-user OAuth 2.0 mailbox connections for Gmail and Microsoft 365.
 *
 * - Initiates OAuth flow (returns authorization URL)
 * - Handles OAuth callback (exchanges code for tokens, stores encrypted)
 * - Refreshes expired access tokens
 * - Disconnects (soft-deletes) mailbox records
 * - Lists active mailboxes for a user
 *
 * Tokens are encrypted at rest using AES-256-CBC with APP_KEY.
 * Uses league/oauth2-client (same as SSOService).
 *
 * Requirements: 13.1
 */

declare(strict_types=1);

namespace CRM\Email;

use Core\BaseService;
use League\OAuth2\Client\Provider\GenericProvider;

class MailboxConnectionService extends BaseService
{
    private const STATE_TTL = 600; // 10 minutes — matches SSOService pattern

    /** @var object Redis client (for OAuth state CSRF protection) */
    private object $redis;

    /** Gmail OAuth 2.0 scopes */
    private const GMAIL_SCOPES = [
        'https://www.googleapis.com/auth/gmail.readonly',
        'https://www.googleapis.com/auth/gmail.send',
        'https://www.googleapis.com/auth/gmail.modify',
    ];

    /** Microsoft 365 Graph API scopes */
    private const MICROSOFT365_SCOPES = [
        'https://graph.microsoft.com/Mail.ReadWrite',
        'https://graph.microsoft.com/Mail.Send',
        'offline_access',
    ];

    public function __construct($db, object $redis)
    {
        parent::__construct($db);
        $this->redis = $redis;
    }

    // -------------------------------------------------------------------------
    // OAuth initiation — Requirement 13.1
    // -------------------------------------------------------------------------

    /**
     * Build the OAuth authorization URL for the given provider.
     *
     * Stores state → "{tenantId}:{userId}" in Redis for CSRF validation.
     *
     * @param  int    $userId
     * @param  string $provider    'gmail' | 'microsoft365'
     * @param  string $tenantId
     * @param  string $companyCode
     * @return string              Authorization URL to redirect the user to
     * @throws \InvalidArgumentException on unsupported provider
     */
    public function initiateOAuth(int $userId, string $provider, string $tenantId, string $companyCode): string
    {
        $this->assertProvider($provider);

        $oauth2  = $this->buildOAuth2Provider($provider);
        $authUrl = $oauth2->getAuthorizationUrl();
        $state   = $oauth2->getState();

        // Store state → "tenantId:userId:companyCode" for callback validation
        $stateValue = "{$tenantId}:{$userId}:{$companyCode}";
        $this->redis->setex("mailbox:state:{$state}", self::STATE_TTL, $stateValue);

        return $authUrl;
    }

    // -------------------------------------------------------------------------
    // OAuth callback — Requirement 13.1
    // -------------------------------------------------------------------------

    /**
     * Handle the OAuth callback: validate state, exchange code for tokens,
     * fetch the mailbox email address, and persist the connection record.
     *
     * @param  string $provider  'gmail' | 'microsoft365'
     * @param  string $code      Authorization code from IdP
     * @param  string $state     CSRF state parameter
     * @param  string $tenantId  Current tenant UUID (for validation)
     * @return array             The inserted connected_mailboxes row
     * @throws \RuntimeException on state mismatch, token exchange failure, or DB error
     */
    public function handleCallback(string $provider, string $code, string $state, string $tenantId): array
    {
        $this->assertProvider($provider);

        if ($state === '') {
            throw new \RuntimeException('Missing OAuth state parameter.', 422);
        }

        // Validate state from Redis
        $storedValue = $this->redis->get("mailbox:state:{$state}");
        if ($storedValue === false || $storedValue === null) {
            throw new \RuntimeException('Invalid or expired OAuth state.', 401);
        }

        $parts = explode(':', $storedValue, 3);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Malformed OAuth state.', 401);
        }

        [$storedTenantId, $userId, $companyCode] = $parts;

        if ($storedTenantId !== $tenantId) {
            throw new \RuntimeException('OAuth state tenant mismatch.', 401);
        }

        // Consume state (one-time use)
        $this->redis->del("mailbox:state:{$state}");

        if ($code === '') {
            throw new \RuntimeException('Missing OAuth authorization code.', 422);
        }

        // Exchange code for tokens
        $oauth2 = $this->buildOAuth2Provider($provider);

        try {
            $token = $oauth2->getAccessToken('authorization_code', ['code' => $code]);
        } catch (\Exception $e) {
            throw new \RuntimeException('OAuth token exchange failed: ' . $e->getMessage(), 401);
        }

        // Fetch email address from provider
        $emailAddress = $this->fetchEmailAddress($provider, $oauth2, $token);

        // Encrypt tokens at rest
        $encryptedAccess  = $this->encryptToken($token->getToken());
        $encryptedRefresh = $this->encryptToken($token->getRefreshToken() ?? '');

        $expiresAt = $token->getExpires()
            ? (new \DateTimeImmutable('@' . $token->getExpires(), new \DateTimeZone('UTC')))->format('Y-m-d H:i:s')
            : (new \DateTimeImmutable('+1 hour', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $now = $this->now();

        // Upsert: if same user+email already exists (soft-deleted or not), restore it
        $existing = $this->db->Execute(
            'SELECT id FROM connected_mailboxes WHERE tenant_id = ? AND user_id = ? AND email_address = ? LIMIT 1',
            [$tenantId, (int) $userId, $emailAddress]
        );

        if ($existing !== false && !$existing->EOF) {
            $mailboxId = (int) $existing->fields['id'];
            $this->db->Execute(
                'UPDATE connected_mailboxes SET provider = ?, access_token = ?, refresh_token = ?,
                 token_expires_at = ?, sync_status = ?, last_error = NULL, deleted_at = NULL,
                 updated_at = ? WHERE id = ?',
                [$provider, $encryptedAccess, $encryptedRefresh, $expiresAt, 'active', $now, $mailboxId]
            );
        } else {
            $rs = $this->db->Execute(
                'INSERT INTO connected_mailboxes
                    (tenant_id, company_code, user_id, provider, email_address,
                     access_token, refresh_token, token_expires_at, sync_status,
                     created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id',
                [$tenantId, $companyCode, (int) $userId, $provider, $emailAddress,
                 $encryptedAccess, $encryptedRefresh, $expiresAt, 'active',
                 (int) $userId, $now, $now]
            );

            if ($rs === false) {
                throw new \RuntimeException('Failed to store mailbox connection: ' . $this->db->ErrorMsg());
            }

            $mailboxId = (!$rs->EOF) ? (int) $rs->fields['id'] : (int) $this->db->Insert_ID();
        }

        return $this->findMailboxById($mailboxId, $tenantId);
    }

    // -------------------------------------------------------------------------
    // Token refresh — Requirement 13.1
    // -------------------------------------------------------------------------

    /**
     * Refresh the access token for a connected mailbox.
     *
     * Updates access_token, refresh_token (if rotated), and token_expires_at.
     *
     * @param  int $mailboxId
     * @return bool  true on success
     * @throws \RuntimeException if mailbox not found or refresh fails
     */
    public function refreshToken(int $mailboxId): bool
    {
        $rs = $this->db->Execute(
            'SELECT * FROM connected_mailboxes WHERE id = ? AND deleted_at IS NULL',
            [$mailboxId]
        );

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException("Mailbox {$mailboxId} not found.");
        }

        $mailbox      = $rs->fields;
        $provider     = $mailbox['provider'];
        $refreshToken = $this->decryptToken($mailbox['refresh_token']);

        if ($refreshToken === '') {
            throw new \RuntimeException("No refresh token available for mailbox {$mailboxId}.");
        }

        $oauth2 = $this->buildOAuth2Provider($provider);

        try {
            $newToken = $oauth2->getAccessToken('refresh_token', ['refresh_token' => $refreshToken]);
        } catch (\Exception $e) {
            // Mark mailbox as error
            $this->db->Execute(
                'UPDATE connected_mailboxes SET sync_status = ?, last_error = ?, updated_at = ? WHERE id = ?',
                ['error', 'Token refresh failed: ' . $e->getMessage(), $this->now(), $mailboxId]
            );
            throw new \RuntimeException('Token refresh failed: ' . $e->getMessage(), 401);
        }

        $encryptedAccess  = $this->encryptToken($newToken->getToken());
        $encryptedRefresh = $newToken->getRefreshToken()
            ? $this->encryptToken($newToken->getRefreshToken())
            : $mailbox['refresh_token']; // keep existing if not rotated

        $expiresAt = $newToken->getExpires()
            ? (new \DateTimeImmutable('@' . $newToken->getExpires(), new \DateTimeZone('UTC')))->format('Y-m-d H:i:s')
            : (new \DateTimeImmutable('+1 hour', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $result = $this->db->Execute(
            'UPDATE connected_mailboxes SET access_token = ?, refresh_token = ?,
             token_expires_at = ?, sync_status = ?, last_error = NULL, updated_at = ?
             WHERE id = ?',
            [$encryptedAccess, $encryptedRefresh, $expiresAt, 'active', $this->now(), $mailboxId]
        );

        return $result !== false && $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // Disconnect — Requirement 13.1
    // -------------------------------------------------------------------------

    /**
     * Soft-delete a mailbox connection (disconnect).
     *
     * @param  int    $mailboxId
     * @param  int    $userId    Must own the mailbox
     * @param  string $tenantId
     * @return bool
     */
    public function disconnect(int $mailboxId, int $userId, string $tenantId): bool
    {
        $now    = $this->now();
        $result = $this->db->Execute(
            'UPDATE connected_mailboxes SET sync_status = ?, deleted_at = ?, updated_at = ?
             WHERE id = ? AND user_id = ? AND tenant_id = ? AND deleted_at IS NULL',
            ['disconnected', $now, $now, $mailboxId, $userId, $tenantId]
        );

        return $result !== false && $this->db->Affected_Rows() > 0;
    }

    // -------------------------------------------------------------------------
    // List mailboxes — Requirement 13.1
    // -------------------------------------------------------------------------

    /**
     * List active (non-deleted) mailboxes for a user within a tenant.
     *
     * @param  int    $userId
     * @param  string $tenantId
     * @return array  Mailbox rows (tokens omitted for security)
     */
    public function getConnectedMailboxes(int $userId, string $tenantId): array
    {
        $rs = $this->db->Execute(
            'SELECT id, tenant_id, company_code, user_id, provider, email_address,
                    token_expires_at, last_sync_at, sync_status, last_error,
                    created_at, updated_at
             FROM connected_mailboxes
             WHERE user_id = ? AND tenant_id = ? AND deleted_at IS NULL
             ORDER BY created_at ASC',
            [$userId, $tenantId]
        );

        if ($rs === false) {
            throw new \RuntimeException('Failed to list mailboxes: ' . $this->db->ErrorMsg());
        }

        $rows = [];
        while (!$rs->EOF) {
            $rows[] = $rs->fields;
            $rs->MoveNext();
        }
        return $rows;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build a league/oauth2-client GenericProvider for the given email provider.
     *
     * @param  string $provider  'gmail' | 'microsoft365'
     * @return GenericProvider
     */
    private function buildOAuth2Provider(string $provider): GenericProvider
    {
        $appUrl      = $_ENV['APP_URL']      ?? getenv('APP_URL')      ?: 'http://localhost';
        $clientId    = '';
        $clientSecret = '';
        $redirectUri = "{$appUrl}/api/v1/crm/email/mailboxes/callback/{$provider}";
        $scopes      = [];
        $urlAuthorize = '';
        $urlToken     = '';
        $urlUserInfo  = '';

        if ($provider === 'gmail') {
            $clientId     = $_ENV['GMAIL_CLIENT_ID']     ?? getenv('GMAIL_CLIENT_ID')     ?: '';
            $clientSecret = $_ENV['GMAIL_CLIENT_SECRET'] ?? getenv('GMAIL_CLIENT_SECRET') ?: '';
            $urlAuthorize = 'https://accounts.google.com/o/oauth2/v2/auth';
            $urlToken     = 'https://oauth2.googleapis.com/token';
            $urlUserInfo  = 'https://openidconnect.googleapis.com/v1/userinfo';
            $scopes       = array_merge(['openid', 'email'], self::GMAIL_SCOPES);
        } else {
            // microsoft365
            $clientId     = $_ENV['MICROSOFT365_CLIENT_ID']     ?? getenv('MICROSOFT365_CLIENT_ID')     ?: '';
            $clientSecret = $_ENV['MICROSOFT365_CLIENT_SECRET'] ?? getenv('MICROSOFT365_CLIENT_SECRET') ?: '';
            $azureTenant  = $_ENV['MICROSOFT365_TENANT']        ?? getenv('MICROSOFT365_TENANT')        ?: 'common';
            $urlAuthorize = "https://login.microsoftonline.com/{$azureTenant}/oauth2/v2.0/authorize";
            $urlToken     = "https://login.microsoftonline.com/{$azureTenant}/oauth2/v2.0/token";
            $urlUserInfo  = 'https://graph.microsoft.com/oidc/userinfo';
            $scopes       = array_merge(['openid', 'email'], self::MICROSOFT365_SCOPES);
        }

        return new GenericProvider([
            'clientId'                => $clientId,
            'clientSecret'            => $clientSecret,
            'redirectUri'             => $redirectUri,
            'urlAuthorize'            => $urlAuthorize,
            'urlAccessToken'          => $urlToken,
            'urlResourceOwnerDetails' => $urlUserInfo,
            'scopes'                  => $scopes,
            'scopeSeparator'          => ' ',
        ]);
    }

    /**
     * Fetch the authenticated user's email address from the provider.
     *
     * @param  string          $provider
     * @param  GenericProvider $oauth2
     * @param  object          $token   AccessToken
     * @return string
     */
    private function fetchEmailAddress(string $provider, GenericProvider $oauth2, object $token): string
    {
        try {
            $owner = $oauth2->getResourceOwner($token);
            $data  = $owner->toArray();
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            if ($email === '') {
                throw new \RuntimeException('Provider did not return an email address.');
            }
            return $email;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to fetch email address: ' . $e->getMessage(), 422);
        }
    }

    /**
     * Encrypt a token string using AES-256-CBC with APP_KEY.
     *
     * @param  string $plaintext
     * @return string  base64-encoded "iv:ciphertext"
     */
    public function encryptToken(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $key = $this->getEncryptionKey();
        $iv  = random_bytes(16);

        $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new \RuntimeException('Token encryption failed.');
        }

        return base64_encode($iv . $ciphertext);
    }

    /**
     * Decrypt a token string encrypted by encryptToken().
     *
     * @param  string $encrypted  base64-encoded "iv:ciphertext"
     * @return string
     */
    public function decryptToken(string $encrypted): string
    {
        if ($encrypted === '') {
            return '';
        }

        $key  = $this->getEncryptionKey();
        $data = base64_decode($encrypted, true);

        if ($data === false || strlen($data) < 17) {
            throw new \RuntimeException('Invalid encrypted token format.');
        }

        $iv         = substr($data, 0, 16);
        $ciphertext = substr($data, 16);

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            throw new \RuntimeException('Token decryption failed.');
        }

        return $plaintext;
    }

    /**
     * Derive a 32-byte encryption key from APP_KEY.
     *
     * @return string  32-byte binary key
     */
    private function getEncryptionKey(): string
    {
        $appKey = $_ENV['APP_KEY'] ?? getenv('APP_KEY') ?: 'default-insecure-key-change-in-production';
        return hash('sha256', $appKey, true); // 32 bytes
    }

    /**
     * Find a mailbox by ID (no tenant scoping — used internally after insert).
     *
     * @param  int    $id
     * @param  string $tenantId
     * @return array
     */
    private function findMailboxById(int $id, string $tenantId): array
    {
        $rs = $this->db->Execute(
            'SELECT id, tenant_id, company_code, user_id, provider, email_address,
                    token_expires_at, last_sync_at, sync_status, last_error,
                    created_at, updated_at
             FROM connected_mailboxes WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException("Mailbox {$id} not found after insert.");
        }

        return $rs->fields;
    }

    /**
     * Assert that the provider is supported.
     *
     * @throws \InvalidArgumentException
     */
    private function assertProvider(string $provider): void
    {
        if (!in_array($provider, ['gmail', 'microsoft365'], true)) {
            throw new \InvalidArgumentException("Unsupported provider '{$provider}'. Use 'gmail' or 'microsoft365'.");
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
