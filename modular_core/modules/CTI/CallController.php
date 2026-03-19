<?php
/**
 * CTI/CallController.php
 *
 * REST endpoints for CTI call management.
 * POST /api/calls/initiate
 * GET  /api/calls/{sid}
 * GET  /api/calls
 * POST /api/calls/{sid}/disposition
 * GET  /api/calls/{sid}/recording
 * DELETE /api/calls/{sid}/recording  (GDPR)
 * GET  /api/calls/dispositions
 */

declare(strict_types=1);

namespace CTI;

use Core\BaseController;
use Core\Response;
use Integrations\Adapters\AdapterFactory;
use Integrations\IntegrationConfigService;

class CallController extends BaseController
{
    private CallLogService      $callLog;
    private PhoneLookupService  $phoneLookup;
    private RecordingService    $recording;
    private DispositionService  $disposition;
    private IntegrationConfigService $configService;

    public function __construct($db, $redis, $queue)
    {
        $this->configService = new IntegrationConfigService($db);
        $this->callLog       = new CallLogService($db, '', '');
        $this->phoneLookup   = new PhoneLookupService($db, $redis, '', '');
        $this->recording     = new RecordingService($db, '');
        $this->disposition   = new DispositionService($db, '', '');
    }

    private function boot(): void
    {
        $this->callLog     = new CallLogService($this->db ?? null, $this->tenantId, $this->companyCode);
        $this->phoneLookup = new PhoneLookupService($this->db ?? null, null, $this->tenantId, $this->companyCode);
        $this->recording   = new RecordingService($this->db ?? null, $this->tenantId);
        $this->disposition = new DispositionService($this->db ?? null, $this->tenantId, $this->companyCode);
    }

    /**
     * POST /api/calls/initiate
     * Body: { platform, to, from, agent_id, contact_id? }
     */
    public function initiate(array $request): Response
    {
        $this->boot();
        $body = $request['body'] ?? [];

        $platform = $body['platform'] ?? 'twilio';
        $config   = $this->configService->getConfig($this->tenantId, $platform);

        if ($config === null) {
            return $this->respond(null, 'Platform not configured', 422);
        }

        $adapter  = AdapterFactory::make($platform, $this->tenantId, $config['credentials'] ?? []);
        $result   = $adapter->makeCall(
            $body['to']   ?? '',
            $body['from'] ?? '',
            $body['callback_url'] ?? ($_ENV['APP_URL'] . '/api/webhooks/' . $platform)
        );

        if (empty($result['call_sid'] ?? $result['sid'] ?? null)) {
            return $this->respond(null, $result['message'] ?? 'Call initiation failed', 502);
        }

        $callSid = $result['call_sid'] ?? $result['sid'];
        $logId   = $this->callLog->create([
            'platform'    => $platform,
            'call_sid'    => $callSid,
            'direction'   => 'outbound',
            'from_number' => $body['from'] ?? '',
            'to_number'   => $body['to']   ?? '',
            'agent_id'    => $body['agent_id'] ?? $this->userId,
            'contact_id'  => $body['contact_id'] ?? null,
            'status'      => 'initiated',
            'raw'         => $result,
        ]);

        return $this->respond(['log_id' => $logId, 'call_sid' => $callSid, 'status' => 'initiated'], null, 201);
    }

    /**
     * GET /api/calls/{sid}
     */
    public function show(string $callSid): Response
    {
        $this->boot();
        $row = $this->callLog->getBySid($callSid);

        if ($row === null) {
            return $this->respond(null, 'Call not found', 404);
        }

        return $this->respond($row);
    }

    /**
     * GET /api/calls
     */
    public function index(array $request): Response
    {
        $this->boot();
        $q      = $request['query'] ?? [];
        $limit  = min((int)($q['limit'] ?? 50), 200);
        $offset = (int)($q['offset'] ?? 0);

        $rows = $this->callLog->list($q, $limit, $offset);
        return $this->respond(['items' => $rows, 'limit' => $limit, 'offset' => $offset]);
    }

    /**
     * POST /api/calls/{sid}/disposition
     * Body: { code, notes }
     */
    public function setDisposition(string $callSid, array $request): Response
    {
        $this->boot();
        $body = $request['body'] ?? [];

        $ok = $this->callLog->updateStatus($callSid, 'completed', [
            'disposition_code'  => $body['code']  ?? null,
            'disposition_notes' => $body['notes'] ?? null,
        ]);

        return $ok
            ? $this->respond(['updated' => true])
            : $this->respond(null, 'Call not found', 404);
    }

    /**
     * GET /api/calls/{sid}/recording
     */
    public function getRecording(string $callSid): Response
    {
        $this->boot();
        $row = $this->callLog->getBySid($callSid);

        if ($row === null) {
            return $this->respond(null, 'Call not found', 404);
        }

        if (empty($row['recording_s3_key'])) {
            return $this->respond(null, 'No recording available', 404);
        }

        $url = $this->recording->presignedUrl($row['recording_s3_key']);
        return $this->respond(['url' => $url, 'expires_in' => 3600]);
    }

    /**
     * DELETE /api/calls/{sid}/recording  — GDPR erasure
     */
    public function deleteRecording(string $callSid): Response
    {
        $this->boot();
        $ok = $this->recording->gdprDelete($callSid);

        return $ok
            ? $this->respond(['deleted' => true])
            : $this->respond(null, 'Recording not found', 404);
    }

    /**
     * GET /api/calls/dispositions
     */
    public function listDispositions(): Response
    {
        $this->boot();
        return $this->respond($this->disposition->list());
    }
}
