<?php
/**
 * Integrations/Attribution/AttributionController.php
 *
 * REST API for lead attribution tracking and reporting.
 */

declare(strict_types=1);

namespace Integrations\Attribution;

use Core\BaseController;
use Core\Response;

class AttributionController extends BaseController
{
    private LeadAttributionService $service;

    public function __construct($db)
    {
        $this->service = new LeadAttributionService($db, $this->tenantId, $this->companyCode);
    }

    /**
     * POST /api/tracking/session
     * Receive session data from JS tracker.
     */
    public function trackSession(array $request): Response
    {
        $sessionId   = $request['session_id']  ?? '';
        $attribution = $request['attribution'] ?? [];
        $deviceType  = $request['device_type'] ?? 'desktop';
        $userAgent   = $request['user_agent']  ?? '';

        if ($sessionId === '') {
            return $this->respond(null, 'session_id required', 400);
        }

        $this->service->saveSession($sessionId, $attribution, $deviceType, $userAgent);

        return $this->respond(['status' => 'tracked']);
    }

    /**
     * GET /api/attribution/summary
     * Dashboard KPIs.
     */
    public function summary(array $request): Response
    {
        $period  = $request['period']  ?? '30d';
        $company = $request['company'] ?? 'all';

        $data = $this->service->getSummary($period, $company);

        return $this->respond($data);
    }

    /**
     * GET /api/attribution/platforms
     * Performance per platform.
     */
    public function platforms(array $request): Response
    {
        $period = $request['period'] ?? '30d';
        $data   = $this->service->getPlatformBreakdown($period);

        return $this->respond($data);
    }

    /**
     * GET /api/attribution/:contactId
     * Attribution for specific contact.
     */
    public function getForContact(int $contactId): Response
    {
        $data = $this->service->getContactAttribution($contactId);

        return $this->respond($data);
    }

    /**
     * POST /api/attribution/capi/resend
     * Retry failed CAPI event.
     */
    public function resendCAPI(array $request): Response
    {
        $eventId = (int)($request['event_id'] ?? 0);
        if ($eventId === 0) {
            return $this->respond(null, 'event_id required', 400);
        }

        $result = $this->service->retryCAPIEvent($eventId);

        return $this->respond($result);
    }
}
