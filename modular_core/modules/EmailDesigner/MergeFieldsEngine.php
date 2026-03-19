<?php
/**
 * EmailDesigner/MergeFieldsEngine.php
 *
 * Replace {{placeholder}} with real data from CRM.
 */

declare(strict_types=1);

namespace EmailDesigner;

class MergeFieldsEngine
{
    public function render(string $template, array $context): string
    {
        return preg_replace_callback(
            '/\{\{([a-zA-Z0-9_.]+)\}\}/',
            function (array $matches) use ($context): string {
                return $this->resolveField($matches[1], $context);
            },
            $template
        );
    }

    private function resolveField(string $field, array $context): string
    {
        $parts  = explode('.', $field, 2);
        $object = $parts[0];
        $key    = $parts[1] ?? null;

        if ($object === 'unsubscribe_url') {
            return $this->generateUnsubscribeUrl($context);
        }

        if ($object === 'tracking_pixel') {
            return $this->generateTrackingPixel($context);
        }

        if ($key && isset($context[$object][$key])) {
            $value = $context[$object][$key];

            // Auto-format dates
            if (str_contains($key, 'date') && $value) {
                return $this->formatDate($value, $context['locale'] ?? 'ar');
            }

            // Auto-format money
            if (str_contains($key, 'amount') || str_contains($key, 'value')) {
                return $this->formatMoney((float)$value, $context['currency'] ?? 'EGP');
            }

            return (string)$value;
        }

        return '';
    }

    public function extractFields(string $template): array
    {
        preg_match_all('/\{\{([a-zA-Z0-9_.]+)\}\}/', $template, $matches);
        return array_unique($matches[1]);
    }

    private function generateUnsubscribeUrl(array $context): string
    {
        $token = $this->generateSecureToken($context['send_id'] ?? 0);
        return ($_ENV['APP_URL'] ?? 'https://app.nexsaas.com') . "/email/unsubscribe/{$token}";
    }

    private function generateTrackingPixel(array $context): string
    {
        $token = $this->generateSecureToken($context['send_id'] ?? 0);
        return '<img src="' . ($_ENV['APP_URL'] ?? 'https://app.nexsaas.com')
             . '/email/track/open/' . $token
             . '" width="1" height="1" style="display:none" alt=""/>';
    }

    private function formatMoney(float $value, string $currency): string
    {
        return number_format($value, 2) . ' ' . $currency;
    }

    private function formatDate(string $date, string $locale): string
    {
        $d = new \DateTime($date);
        return $locale === 'ar' ? $d->format('d/m/Y') : $d->format('M j, Y');
    }

    private function generateSecureToken(int $sendId): string
    {
        return hash_hmac('sha256', (string)$sendId, $_ENV['APP_KEY'] ?? 'secret');
    }
}
