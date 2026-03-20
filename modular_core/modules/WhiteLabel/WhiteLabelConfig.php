<?php

namespace ModularCore\Modules\WhiteLabel;

/**
 * White-label Branding Resolver (S2 Roadmap)
 * 
 * Fetches and resolves brand configuration for the current domain or tenant.
 */
class WhiteLabelConfig
{
    private $db;
    private $redis;

    public function __construct($db, $redis)
    {
        $this->db = $db;
        $this->redis = $redis;
    }

    /**
     * Resolve Brand Config for a Tenant
     */
    public function resolve($tenantId)
    {
        $cacheKey = "wl_config_{$tenantId}";
        $cached = $this->redis->get($cacheKey);
        
        if ($cached) return json_decode($cached, true);

        $config = $this->db->queryOne("
            SELECT product_name, logo_url, fav_url, primary_color, accent_color, custom_domain 
            FROM white_label_configs 
            WHERE tenant_id = :id AND active = 1
        ", ['id' => $tenantId]);

        if ($config) {
            $this->redis->set($cacheKey, json_encode($config), 3600); // Cache for 1 hour
            return $config;
        }

        return $this->getDefaults();
    }

    /**
     * Resolve Tenant from Custom Domain (Crocker Level Mapping)
     */
    public function resolveFromDomain($domain)
    {
        $tenantId = $this->db->queryValue("
            SELECT tenant_id FROM white_label_configs 
            WHERE custom_domain = :domain AND active = 1
        ", ['domain' => $domain]);

        return $tenantId ?: null;
    }

    public function getDefaults()
    {
        return [
            'product_name'   => 'NexSaaS CRM',
            'logo_url'       => '/assets/logo-nex.png',
            'primary_color'  => '#1d4ed8',
            'accent_color'   => '#3b82f6',
            'custom_domain'  => null
        ];
    }
}
