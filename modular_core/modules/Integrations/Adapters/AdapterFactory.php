<?php
/**
 * Integrations/Adapters/AdapterFactory.php
 *
 * Creates the correct adapter instance for a given platform slug.
 */

declare(strict_types=1);

namespace Integrations\Adapters;

class AdapterFactory
{
    private static array $map = [
        'twilio'          => TwilioAdapter::class,
        'whatsapp_meta'   => WhatsAppMetaAdapter::class,
        'vodafone_egypt'  => VodafoneEgyptAdapter::class,
        'orange_egypt'    => OrangeEgyptAdapter::class,
        'unifonic'        => UnifonicAdapter::class,
        'infobip'         => InfobipAdapter::class,
        'asterisk'        => AsteriskAdapter::class,
        'telegram'        => TelegramAdapter::class,
    ];

    /**
     * @param  string $platform  e.g. 'twilio', 'vodafone_egypt'
     * @param  string $tenantId
     * @param  array  $config    Decrypted credentials from integration_configs
     * @return BaseAdapter
     * @throws \InvalidArgumentException
     */
    public static function make(string $platform, string $tenantId, array $config): BaseAdapter
    {
        $class = self::$map[$platform] ?? null;
        if ($class === null) {
            throw new \InvalidArgumentException("Unknown integration platform: {$platform}");
        }
        return new $class($tenantId, $config);
    }

    public static function register(string $platform, string $class): void
    {
        self::$map[$platform] = $class;
    }

    public static function supported(): array
    {
        return array_keys(self::$map);
    }
}
