<?php
/**
 * Integrations/WebhookController.php
 *
 * Unified webhook receiver for all integration platforms.
 * Pattern: verify → queue to RabbitMQ → respond < 2s.
 */

declare(strict_types=1);

namespace Integrations;

use Core\BaseController;
use Core\Response;
use Integrations\Adapters\AdapterFactory;

class WebhookController extends BaseController
{
    private IntegrationConfigService $configService;
    private $queue; // RabbitMQ client

    public function __construct($db, $queue)
    {
        $this->configService = new IntegrationConfigService($db);
        $this->queue         = $queue;
    }

    /**
     * Handle inbound webhook from any platform.
     * GET  → webhook verification (Meta, Google)
     * POST → inbound event
     */
    public function handle(string $platform, array $request): Response
    {
        $tenantId = $this->resolveTenantFromRequest($request);
        $config   = $this->configService->getConfig($tenantId, $platform);

        if ($config === null) {
            return $this->respond(null, 'Integration not configured', 404);
        }

        $adapter = AdapterFactory::make($platform, $tenantId, $config['credentials'] ?? []);

        // GET: webhook verification (Meta, Google lead forms)
        if (($request['method'] ?? 'POST') === 'GET') {
            $challenge = $adapter->verifyWebhook($request['headers'] ?? [], '');
            if ($challenge) {
                return $this->respond(['challenge' => $challenge]);
            }
            return $this->respond(null, 'Verification failed', 403);
        }

        $rawBody = $request['raw_body'] ?? '';
        $headers = $request['headers'] ?? [];

        // Verify signature
        if (!$adapter->verifyWebhook($headers, $rawBody)) {
            return $this->respond(null, 'Invalid webhook signature', 403);
        }

        // Parse to normalised format
        $payload = json_decode($rawBody, true) ?? [];
        $normalised = $adapter->parseInboundWebhook($payload);

        if (empty($normalised)) {
            return $this->respond(['status' => 'ignored']);
        }

        // Normalise to array of events
        if (!isset($normalised[0])) {
            $normalised = [$normalised];
        }

        foreach ($normalised as $event) {
            if ($event['is_test'] ?? false) {
                continue;
            }
            $this->queue->publish('webhook.' . $platform . '.inbound', [
                'tenant_id'   => $tenantId,
                'platform'    => $platform,
                'payload'     => $event,
                'received_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]);
        }

        return $this->respond(['status' => 'queued']);
    }

    private function resolveTenantFromRequest(array $request): string
    {
        // Resolve tenant from JWT, subdomain, or webhook secret lookup
        return $request['tenant_id'] ?? $this->tenantId;
    }
}
