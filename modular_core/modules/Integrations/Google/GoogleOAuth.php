<?php

namespace ModularCore\Modules\Integrations\Google;

use GuzzleHttp\Client;
use Exception;

/**
 * Google OAuth Manager: Secure Google Workspace Authorization (Requirement I3)
 * Orchestrates Gmail, Calendar, and Drive scopes per Agent.
 */
class GoogleOAuth
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $authEndpoint = 'https://accounts.google.com/o/oauth2/v2/auth';
    private $tokenEndpoint = 'https://oauth2.googleapis.com/token';

    public function __construct()
    {
        $this->clientId = env('GOOGLE_CLIENT_ID');
        $this->clientSecret = env('GOOGLE_CLIENT_SECRET');
        $this->redirectUri = env('GOOGLE_REDIRECT_URI');
    }

    /**
     * Requirement 683: Authorization Flow (Generate Auth URL)
     */
    public function getAuthUrl($tenantId, $userId)
    {
        $scopes = [
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/calendar.events',
            'https://www.googleapis.com/auth/drive.readonly'
        ];

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline', // Requirement: Refresh token for background sync
            'prompt' => 'consent',
            'state' => encrypt(['tenant_id' => $tenantId, 'user_id' => $userId])
        ];

        return "{$this->authEndpoint}?" . http_build_query($params);
    }

    /**
     * Requirement 683: Secure Token Exchange & Storage
     */
    public function handleCallback($code, $state)
    {
        $client = new Client();
        $response = $client->post($this->tokenEndpoint, [
            'form_params' => [
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
            ]
        ]);

        $tokens = json_decode($response->getBody()->getContents(), true);
        $meta = decrypt($state);

        // Requirement: Per-user token storage in database
        \DB::table('google_accounts')->updateOrInsert(
            ['tenant_id' => $meta['tenant_id'], 'user_id' => $meta['user_id']],
            [
                'access_token' => encrypt($tokens['access_token']),
                'refresh_token' => encrypt($tokens['refresh_token'] ?? null),
                'expires_at' => now()->addSeconds($tokens['expires_in']),
                'updated_at' => now()
            ]
        );

        return $tokens;
    }

    /**
     * Requirement: Automated Token Refresh for Background Jobs (Cron)
     */
    public function refreshToken($userId)
    {
        $account = \DB::table('google_accounts')->where('user_id', $userId)->first();
        if (!$account || !$account->refresh_token) {
            throw new Exception("No refresh token available for user {$userId}");
        }

        $client = new Client();
        $response = $client->post($this->tokenEndpoint, [
            'form_params' => [
                'refresh_token' => decrypt($account->refresh_token),
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
            ]
        ]);

        $tokens = json_decode($response->getBody()->getContents(), true);
        
        \DB::table('google_accounts')->where('user_id', $userId)->update([
            'access_token' => encrypt($tokens['access_token']),
            'expires_at' => now()->addSeconds($tokens['expires_in']),
        ]);

        return $tokens['access_token'];
    }
}
