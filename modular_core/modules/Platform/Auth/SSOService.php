<?php
/**
 * Platform/Auth/SSOService.php
 *
 * Single Sign-On service supporting SAML 2.0 and OAuth 2.0/OIDC.
 *
 * Supported providers:
 *   - 'saml'      → SAML 2.0 SP-initiated flow via onelogin/php-saml
 *   - 'google'    → OAuth 2.0/OIDC via league/oauth2-client
 *   - 'microsoft' → OAuth 2.0/OIDC via league/oauth2-client
 *   - 'github'    → OAuth 2.0 via league/oauth2-client
 *
 * Redis keys:
 *   sso:state:{state}  → tenant_id  (TTL 600 s) — CSRF protection for OAuth2
 *
 * Requirements: 4.7, 34.1, 34.2, 34.3, 34.4, 34.5
 */

declare(strict_types=1);

namespace Platform\Auth;

use Core\BaseService;
use Firebase\JWT\JWT;
use OneLogin\Saml2\Auth as SamlAuth;
use OneLogin\Saml2\Settings as SamlSettings;
use League\OAuth2\Client\Provider\GenericProvider;

class SSOService extends BaseService
{
    private const ACCESS_TOKEN_TTL  = 900;    // 15 minutes
    private const REFRESH_TOKEN_TTL = 604800; // 7 days
    private const STATE_TTL         = 600;    // 10 minutes for OAuth2 state
    private const PRIVATE_KEY_PATH  = __DIR__ . '/../../../keys/jwt_private.pem';

    /** @var object Redis client */
    private object $redis;

    /**
     * @param \ADOConnection $db    ADOdb connection
     * @param object         $redis Redis client
     */
    public function __construct($db, object $redis)
    {
        parent::__construct($db);
        $this->redis = $redis;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Initiate an SSO flow for the given provider.
     *
     * For SAML: builds a SAML AuthnRequest and returns the IdP redirect URL.
     * For OAuth2: builds an authorization URL with a CSRF state param stored
     *             in Redis key sso:state:{state} with TTL 600 s.
     *
     * Requirements: 34.1, 34.2
     *
     * @param  string $provider  'saml' | 'google' | 'microsoft' | 'github'
     * @param  string $tenantId  Current tenant UUID
     * @return string            Redirect URL to send the browser to
     * @throws \RuntimeException if provider not configured or unsupported
     */
    public function initiate(string $provider, string $tenantId): string
    {
        $config = $this->loadProviderConfig($provider, $tenantId);

        if ($provider === 'saml') {
            return $this->initiateSaml($config);
        }

        return $this->initiateOAuth2($provider, $config, $tenantId);
    }

    /**
     * Handle the IdP callback and return tokens.
     *
     * For SAML: processes the SAMLResponse POST, extracts NameID + attributes.
     * For OAuth2: validates state, exchanges code for token, fetches user info.
     *
     * Auto-provisions a new user if none exists for email + tenant_id.
     * Maps IdP groups to RBAC roles via sso_role_mappings table.
     * Issues JWT + refresh token.
     *
     * Requirements: 34.2, 34.3, 34.4
     *
     * @param  string $provider  'saml' | 'google' | 'microsoft' | 'github'
     * @param  array  $params    Request params (POST body or query string)
     * @param  string $tenantId  Current tenant UUID
     * @return array{access_token: string, refresh_token: string, expires_in: int, provisioned: bool}
     * @throws \RuntimeException on validation failure or missing config
     */
    public function handleCallback(string $provider, array $params, string $tenantId): array
    {
        $config = $this->loadProviderConfig($provider, $tenantId);

        if ($provider === 'saml') {
            $idpUser = $this->processSamlCallback($config, $params);
        } else {
            $idpUser = $this->processOAuth2Callback($provider, $config, $params, $tenantId);
        }

        // Auto-provision or fetch existing user
        [$user, $provisioned] = $this->provisionUser($idpUser, $tenantId);

        // Issue tokens
        $accessToken  = $this->issueAccessToken($user, $tenantId);
        $refreshToken = $this->issueRefreshToken((int) $user['id']);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => self::ACCESS_TOKEN_TTL,
            'provisioned'   => $provisioned,
        ];
    }

    // -------------------------------------------------------------------------
    // SAML helpers
    // -------------------------------------------------------------------------

    /**
     * Build SAML AuthnRequest and return the IdP redirect URL.
     *
     * @param  array $config  Provider config from sso_providers.config JSONB
     * @return string         IdP SSO URL with SAMLRequest param
     */
    private function initiateSaml(array $config): string
    {
        $saml = new SamlAuth($this->buildSamlSettings($config));
        return $saml->login(null, [], false, false, true);
    }

    /**
     * Process a SAML 2.0 callback (SAMLResponse POST).
     *
     * Extracts NameID as the subject identifier and maps standard attributes
     * (email, name, groups) from the assertion.
     *
     * @param  array $config  Provider config
     * @param  array $params  POST params (must contain 'SAMLResponse')
     * @return array{subject: string, email: string, name: string, groups: string[]}
     * @throws \RuntimeException on SAML validation failure
     */
    private function processSamlCallback(array $config, array $params): array
    {
        $saml = new SamlAuth($this->buildSamlSettings($config));
        $saml->processResponse($params['SAMLResponse'] ?? null);

        $errors = $saml->getErrors();
        if (!empty($errors)) {
            throw new \RuntimeException(
                'SAML validation failed: ' . implode(', ', $errors),
                401
            );
        }

        if (!$saml->isAuthenticated()) {
            throw new \RuntimeException('SAML authentication failed.', 401);
        }

        $nameId     = $saml->getNameId();
        $attributes = $saml->getAttributes();

        // Normalize common attribute names across IdPs
        $email  = $this->extractSamlAttribute($attributes, [
            'email', 'mail', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
        ]);
        $name   = $this->extractSamlAttribute($attributes, [
            'displayName', 'cn', 'name',
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
        ]) ?? $nameId;
        $groups = $this->extractSamlAttributeArray($attributes, [
            'groups', 'memberOf', 'role',
            'http://schemas.microsoft.com/ws/2008/06/identity/claims/groups',
        ]);

        if (empty($email)) {
            throw new \RuntimeException('SAML response missing email attribute.', 422);
        }

        return [
            'subject'  => $nameId,
            'email'    => strtolower(trim($email)),
            'name'     => $name,
            'groups'   => $groups,
            'provider' => 'saml',
        ];
    }

    /**
     * Build the onelogin/php-saml settings array from stored config.
     *
     * @param  array $config  JSONB config from sso_providers
     * @return array          Settings array for SamlAuth constructor
     */
    private function buildSamlSettings(array $config): array
    {
        return [
            'strict' => true,
            'debug'  => false,
            'sp'     => [
                'entityId'                 => $config['sp_entity_id'] ?? '',
                'assertionConsumerService' => [
                    'url'     => $config['sp_acs_url'] ?? '',
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                ],
                'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            ],
            'idp' => [
                'entityId'            => $config['entity_id'] ?? '',
                'singleSignOnService' => [
                    'url'     => $config['sso_url'] ?? '',
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'singleLogoutService' => [
                    'url'     => $config['slo_url'] ?? '',
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'x509cert' => $config['x509cert'] ?? '',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // OAuth 2.0 / OIDC helpers
    // -------------------------------------------------------------------------

    /**
     * Build OAuth2 authorization URL and store state in Redis.
     *
     * @param  string $provider  'google' | 'microsoft' | 'github'
     * @param  array  $config    Provider config from sso_providers.config
     * @param  string $tenantId  Current tenant UUID (stored with state)
     * @return string            Authorization URL
     */
    private function initiateOAuth2(string $provider, array $config, string $tenantId): string
    {
        $oauth2 = $this->buildOAuth2Provider($provider, $config);

        $authUrl = $oauth2->getAuthorizationUrl();
        $state   = $oauth2->getState();

        // Store state → tenantId in Redis for CSRF validation (TTL 600 s)
        $this->redis->setex("sso:state:{$state}", self::STATE_TTL, $tenantId);

        return $authUrl;
    }

    /**
     * Process OAuth2 callback: validate state, exchange code, fetch user info.
     *
     * @param  string $provider  'google' | 'microsoft' | 'github'
     * @param  array  $config    Provider config
     * @param  array  $params    Query params (code, state)
     * @param  string $tenantId  Current tenant UUID
     * @return array{subject: string, email: string, name: string, groups: string[], provider: string}
     * @throws \RuntimeException on state mismatch or token exchange failure
     */
    private function processOAuth2Callback(
        string $provider,
        array  $config,
        array  $params,
        string $tenantId
    ): array {
        $state = $params['state'] ?? '';
        $code  = $params['code']  ?? '';

        if ($state === '') {
            throw new \RuntimeException('Missing OAuth2 state parameter.', 422);
        }

        // Validate state against Redis
        $storedTenantId = $this->redis->get("sso:state:{$state}");
        if ($storedTenantId === false || $storedTenantId === null) {
            throw new \RuntimeException('Invalid or expired OAuth2 state.', 401);
        }
        if ($storedTenantId !== $tenantId) {
            throw new \RuntimeException('OAuth2 state tenant mismatch.', 401);
        }

        // Consume state (one-time use)
        $this->redis->del("sso:state:{$state}");

        if ($code === '') {
            throw new \RuntimeException('Missing OAuth2 authorization code.', 422);
        }

        $oauth2 = $this->buildOAuth2Provider($provider, $config);

        try {
            $token = $oauth2->getAccessToken('authorization_code', ['code' => $code]);
        } catch (\Exception $e) {
            throw new \RuntimeException('OAuth2 token exchange failed: ' . $e->getMessage(), 401);
        }

        // Fetch user info from the userinfo endpoint
        try {
            $resourceOwner = $oauth2->getResourceOwner($token);
            $ownerArray    = $resourceOwner->toArray();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to fetch OAuth2 user info: ' . $e->getMessage(), 401);
        }

        return $this->normalizeOAuth2User($provider, $ownerArray);
    }

    /**
     * Normalize OAuth2 user info across providers into a common shape.
     *
     * @param  string $provider
     * @param  array  $ownerArray  Raw resource owner data
     * @return array{subject: string, email: string, name: string, groups: string[], provider: string}
     */
    private function normalizeOAuth2User(string $provider, array $ownerArray): array
    {
        $subject = (string) ($ownerArray['sub'] ?? $ownerArray['id'] ?? '');
        $email   = strtolower(trim((string) ($ownerArray['email'] ?? '')));
        $name    = (string) ($ownerArray['name'] ?? $ownerArray['login'] ?? $email);

        // Groups/roles may come from custom claims (e.g. Microsoft groups claim)
        $groups = [];
        if (!empty($ownerArray['groups']) && is_array($ownerArray['groups'])) {
            $groups = array_values(array_filter(array_map('strval', $ownerArray['groups'])));
        }

        if ($email === '') {
            throw new \RuntimeException('OAuth2 user info missing email.', 422);
        }
        if ($subject === '') {
            $subject = $email; // fallback for providers that omit sub
        }

        return [
            'subject'  => $subject,
            'email'    => $email,
            'name'     => $name,
            'groups'   => $groups,
            'provider' => $provider,
        ];
    }

    /**
     * Build a league/oauth2-client provider instance.
     *
     * @param  string $provider  'google' | 'microsoft' | 'github'
     * @param  array  $config    Provider config from sso_providers.config
     * @return \League\OAuth2\Client\Provider\AbstractProvider
     * @throws \RuntimeException for unsupported providers
     */
    private function buildOAuth2Provider(string $provider, array $config): object
    {
        $clientId     = $config['client_id']     ?? '';
        $clientSecret = $config['client_secret'] ?? '';
        $redirectUri  = $config['redirect_uri']  ?? '';
        $scopes       = $config['scopes']         ?? [];

        switch ($provider) {
            case 'google':
                return new GenericProvider([
                    'clientId'                => $clientId,
                    'clientSecret'            => $clientSecret,
                    'redirectUri'             => $redirectUri,
                    'urlAuthorize'            => 'https://accounts.google.com/o/oauth2/v2/auth',
                    'urlAccessToken'          => 'https://oauth2.googleapis.com/token',
                    'urlResourceOwnerDetails' => 'https://openidconnect.googleapis.com/v1/userinfo',
                    'scopes'                  => array_merge(['openid', 'email', 'profile'], $scopes),
                    'scopeSeparator'          => ' ',
                ]);

            case 'microsoft':
                $tenantSlug = $config['azure_tenant'] ?? 'common';
                return new GenericProvider([
                    'clientId'                => $clientId,
                    'clientSecret'            => $clientSecret,
                    'redirectUri'             => $redirectUri,
                    'urlAuthorize'            => "https://login.microsoftonline.com/{$tenantSlug}/oauth2/v2.0/authorize",
                    'urlAccessToken'          => "https://login.microsoftonline.com/{$tenantSlug}/oauth2/v2.0/token",
                    'urlResourceOwnerDetails' => 'https://graph.microsoft.com/oidc/userinfo',
                    'scopes'                  => array_merge(['openid', 'email', 'profile'], $scopes),
                    'scopeSeparator'          => ' ',
                ]);

            case 'github':
                return new GenericProvider([
                    'clientId'                => $clientId,
                    'clientSecret'            => $clientSecret,
                    'redirectUri'             => $redirectUri,
                    'urlAuthorize'            => 'https://github.com/login/oauth/authorize',
                    'urlAccessToken'          => 'https://github.com/login/oauth/access_token',
                    'urlResourceOwnerDetails' => 'https://api.github.com/user',
                    'scopes'                  => array_merge(['user:email'], $scopes),
                    'scopeSeparator'          => ' ',
                ]);

            default:
                throw new \RuntimeException("Unsupported OAuth2 provider: {$provider}", 400);
        }
    }

    // -------------------------------------------------------------------------
    // User provisioning
    // -------------------------------------------------------------------------

    /**
     * Find or auto-provision a user from IdP attributes.
     *
     * Lookup order:
     *  1. By sso_provider + sso_subject + tenant_id (most specific)
     *  2. By email + tenant_id (handles pre-existing local accounts)
     *
     * On first SSO login: INSERT new user with is_active=true, random bcrypt
     * password_hash, and platform_role derived from IdP group mapping.
     *
     * Requirements: 34.3, 34.4
     *
     * @param  array  $idpUser   Normalized IdP user data
     * @param  string $tenantId  Current tenant UUID
     * @return array{0: array, 1: bool}  [user row, was_provisioned]
     */
    private function provisionUser(array $idpUser, string $tenantId): array
    {
        $provider = $idpUser['provider'];
        $subject  = $idpUser['subject'];
        $email    = $idpUser['email'];
        $name     = $idpUser['name'] ?? $email;
        $groups   = $idpUser['groups'] ?? [];

        // 1. Look up by SSO subject (returning user)
        $rs = $this->db->Execute(
            "SELECT id, email, platform_role, accounting_role, company_code, is_active
             FROM users
             WHERE tenant_id = ? AND sso_provider = ? AND sso_subject = ?
               AND deleted_at IS NULL",
            [$tenantId, $provider, $subject]
        );

        if ($rs !== false && !$rs->EOF) {
            return [$rs->fields, false];
        }

        // 2. Look up by email (pre-existing local account)
        $rs = $this->db->Execute(
            "SELECT id, email, platform_role, accounting_role, company_code, is_active
             FROM users
             WHERE tenant_id = ? AND email = ? AND deleted_at IS NULL",
            [$tenantId, $email]
        );

        if ($rs !== false && !$rs->EOF) {
            $user = $rs->fields;
            // Link SSO identity to existing account
            $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $this->db->Execute(
                "UPDATE users SET sso_provider = ?, sso_subject = ?, updated_at = ?
                 WHERE id = ? AND tenant_id = ?",
                [$provider, $subject, $now, (int) $user['id'], $tenantId]
            );
            return [$user, false];
        }

        // 3. Auto-provision new user
        $role         = $this->mapGroupsToRoles($groups, $tenantId, $provider);
        $companyCode  = '01'; // default company
        $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 12]);
        $now          = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->db->Execute(
            "INSERT INTO users
                (tenant_id, company_code, email, password_hash, full_name,
                 platform_role, is_active, sso_provider, sso_subject,
                 created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, TRUE, ?, ?, ?, ?)",
            [$tenantId, $companyCode, $email, $passwordHash, $name,
             $role, $provider, $subject, $now, $now]
        );

        // Fetch the newly inserted user
        $rs = $this->db->Execute(
            "SELECT id, email, platform_role, accounting_role, company_code, is_active
             FROM users
             WHERE tenant_id = ? AND email = ? AND deleted_at IS NULL
             ORDER BY id DESC LIMIT 1",
            [$tenantId, $email]
        );

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException('Failed to provision SSO user.', 500);
        }

        return [$rs->fields, true];
    }

    /**
     * Map IdP groups to a platform RBAC role via sso_role_mappings table.
     *
     * Iterates the user's groups in order; returns the first matching role.
     * Falls back to 'Agent' if no mapping is found.
     *
     * Requirements: 34.4
     *
     * @param  string[] $groups    IdP group names
     * @param  string   $tenantId  Current tenant UUID
     * @param  string   $provider  Provider name
     * @return string              platform_role value
     */
    private function mapGroupsToRoles(array $groups, string $tenantId, string $provider): string
    {
        if (empty($groups)) {
            return 'Agent';
        }

        $placeholders = implode(',', array_fill(0, count($groups), '?'));
        $params       = array_merge([$tenantId, $provider], $groups);

        $rs = $this->db->Execute(
            "SELECT idp_group, rbac_role FROM sso_role_mappings
             WHERE tenant_id = ? AND provider_name = ?
               AND idp_group IN ({$placeholders})
               AND deleted_at IS NULL
             LIMIT 1",
            $params
        );

        if ($rs !== false && !$rs->EOF) {
            return $rs->fields['rbac_role'];
        }

        return 'Agent'; // default fallback
    }

    // -------------------------------------------------------------------------
    // Token issuance (mirrors AuthService logic)
    // -------------------------------------------------------------------------

    /**
     * Issue an RS256 JWT access token for the given user.
     *
     * @param  array  $user      User row from DB
     * @param  string $tenantId  Current tenant UUID
     * @return string            Signed JWT
     */
    private function issueAccessToken(array $user, string $tenantId): string
    {
        if (!file_exists(self::PRIVATE_KEY_PATH)) {
            throw new \RuntimeException('JWT private key not found.', 500);
        }

        $pem        = file_get_contents(self::PRIVATE_KEY_PATH);
        $privateKey = openssl_pkey_get_private($pem);

        if ($privateKey === false) {
            throw new \RuntimeException('Failed to load JWT private key.', 500);
        }

        $now   = time();
        $roles = array_values(array_filter([
            $user['platform_role']   ?? null,
            $user['accounting_role'] ?? null,
        ]));

        $payload = [
            'sub'          => (int) $user['id'],
            'tenant_id'    => $tenantId,
            'company_code' => $user['company_code'],
            'roles'        => $roles,
            'iat'          => $now,
            'exp'          => $now + self::ACCESS_TOKEN_TTL,
        ];

        return JWT::encode($payload, $privateKey, 'RS256');
    }

    /**
     * Issue a refresh token and store it in Redis.
     *
     * Redis key: refresh:{sha256(token)}, value: user_id, TTL 604800
     *
     * @param  int    $userId
     * @return string Raw hex refresh token
     */
    private function issueRefreshToken(int $userId): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $hash     = hash('sha256', $rawToken);

        $this->redis->setex("refresh:{$hash}", self::REFRESH_TOKEN_TTL, (string) $userId);

        return $rawToken;
    }

    // -------------------------------------------------------------------------
    // Config loader
    // -------------------------------------------------------------------------

    /**
     * Load provider config from sso_providers table.
     *
     * @param  string $provider  Provider name
     * @param  string $tenantId  Tenant UUID
     * @return array             Decoded JSONB config
     * @throws \RuntimeException if provider not found or inactive
     */
    private function loadProviderConfig(string $provider, string $tenantId): array
    {
        $allowed = ['saml', 'google', 'microsoft', 'github'];
        if (!in_array($provider, $allowed, true)) {
            throw new \RuntimeException("Unsupported SSO provider: {$provider}", 400);
        }

        $rs = $this->db->Execute(
            "SELECT config FROM sso_providers
             WHERE tenant_id = ? AND provider_name = ?
               AND is_active = TRUE AND deleted_at IS NULL",
            [$tenantId, $provider]
        );

        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException(
                "SSO provider '{$provider}' is not configured for this tenant.",
                404
            );
        }

        $config = json_decode($rs->fields['config'], true);

        if (!is_array($config)) {
            throw new \RuntimeException("Invalid SSO provider config for '{$provider}'.", 500);
        }

        return $config;
    }

    // -------------------------------------------------------------------------
    // SAML attribute extraction helpers
    // -------------------------------------------------------------------------

    /**
     * Extract the first non-empty value from a list of candidate attribute names.
     *
     * @param  array    $attributes  SAML attributes map
     * @param  string[] $candidates  Attribute names to try in order
     * @return string|null
     */
    private function extractSamlAttribute(array $attributes, array $candidates): ?string
    {
        foreach ($candidates as $key) {
            if (!empty($attributes[$key][0])) {
                return (string) $attributes[$key][0];
            }
        }
        return null;
    }

    /**
     * Extract all values from the first matching multi-value SAML attribute.
     *
     * @param  array    $attributes  SAML attributes map
     * @param  string[] $candidates  Attribute names to try in order
     * @return string[]
     */
    private function extractSamlAttributeArray(array $attributes, array $candidates): array
    {
        foreach ($candidates as $key) {
            if (!empty($attributes[$key]) && is_array($attributes[$key])) {
                return array_values(array_filter(array_map('strval', $attributes[$key])));
            }
        }
        return [];
    }
}
