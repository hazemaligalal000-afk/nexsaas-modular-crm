<?php
/**
 * ModularCore/Modules/Platform/Security/GoogleOAuthService.php
 * Google Workspace OAuth 2.0 Integration (Requirement 10.1)
 */

namespace ModularCore\Modules\Platform\Security;

use Core\Database;

class GoogleOAuthService {
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    public function __construct() {
        $this->clientId = getenv('GOOGLE_CLIENT_ID');
        $this->clientSecret = getenv('GOOGLE_CLIENT_SECRET');
        $this->redirectUri = getenv('APP_URL') . '/api/auth/google/callback';
    }

    public function getAuthUrl() {
        // Build OAuth 2.0 Authorization URL
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'consent'
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function handleCallback($authCode) {
        // Exchange auth code for access token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $authCode,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri
        ]));
        
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (empty($response['access_token'])) {
            throw new \Exception("Failed to exchange Google OAuth code.");
        }

        // Fetch User Profile
        $chProfile = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt($chProfile, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chProfile, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $response['access_token']]);
        
        $profile = json_decode(curl_exec($chProfile), true);
        curl_close($chProfile);

        return $this->resolveUser($profile);
    }

    private function resolveUser($profile) {
        $pdo = Database::getCentralConnection();
        // Check if user exists via Google ID or Email
        $stmt = $pdo->prepare("SELECT user_id, tenant_id FROM users WHERE google_id = ? OR email = ?");
        $stmt->execute([$profile['id'], $profile['email']]);
        $user = $stmt->fetch();

        if (!$user) {
            // JIT Provisioning logic would go here if enabled for the tenant
            throw new \Exception("User not registered in the CRM network.");
        }

        return [
            'user_id' => $user['user_id'],
            'tenant_id' => $user['tenant_id'],
            'email' => $profile['email']
        ];
    }
}
