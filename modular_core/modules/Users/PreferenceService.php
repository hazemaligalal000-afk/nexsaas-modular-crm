<?php
/**
 * Users/PreferenceService.php
 * 
 * CORE → ADVANCED: Personalized User Experience
 */

declare(strict_types=1);

namespace Modules\Users;

use Core\BaseService;

class PreferenceService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get user preferences (Language, RTL, Theme)
     * Used by: App.tsx / I18nProvider
     */
    public function getPreferences(int $userId): array
    {
        $sql = "SELECT lang, theme, notify_channels, timezone 
                FROM user_preferences WHERE user_id = ?";
        
        $prefs = $this->db->GetRow($sql, [$userId]);

        if (!$prefs) {
            return [
                'lang' => 'ar', // Default Arabic for the region
                'theme' => 'light',
                'rtl' => true,
                'notify_channels' => ['email', 'waba']
            ];
        }

        return array_merge($prefs, [
            'rtl' => ($prefs['lang'] === 'ar')
        ]);
    }

    /**
     * Update user settings
     */
    public function updatePreferences(int $userId, array $data): void
    {
        $sql = "INSERT INTO user_preferences (user_id, lang, theme, notify_channels, timezone)
                VALUES (?, ?, ?, ?, ?)
                ON CONFLICT (user_id) DO UPDATE SET 
                    lang = EXCLUDED.lang, 
                    theme = EXCLUDED.theme, 
                    notify_channels = EXCLUDED.notify_channels, 
                    timezone = EXCLUDED.timezone,
                    updated_at = NOW()";
        
        $this->db->Execute($sql, [
            $userId, 
            $data['lang'] ?? 'ar', 
            $data['theme'] ?? 'light', 
            json_encode($data['notify_channels'] ?? ['email']),
            $data['timezone'] ?? 'UTC'
        ]);
    }
}
