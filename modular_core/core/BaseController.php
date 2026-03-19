<?php
/**
 * Core/BaseController.php
 *
 * Abstract base controller that wraps every REST API response in the standard
 * API_Response envelope defined in the design document.
 *
 * Envelope shape:
 * {
 *   "success":  bool,
 *   "data":     mixed,
 *   "error":    string|null,
 *   "meta": {
 *     "company_code": string,
 *     "tenant_id":    string,
 *     "user_id":      string,
 *     "currency":     string,
 *     "fin_period":   string,
 *     "timestamp":    string   // UTC ISO-8601
 *   }
 * }
 *
 * Requirements: 3.1, 3.2, 3.3, 3.4
 */

declare(strict_types=1);

namespace Core;

/**
 * Lightweight value object representing an HTTP response.
 * Controllers return this from respond(); the front-controller sends it.
 */
final class Response
{
    public array $body;
    public int $status;

    public function __construct(array $body, int $status = 200) {
        $this->body = $body;
        $this->status = $status;
    }
}

abstract class BaseController
{
    /** @var string  Current tenant UUID */
    protected string $tenantId = '';

    /** @var string  Authenticated user ID */
    protected string $userId = '';

    /** @var string  Two-digit company code, e.g. '01'–'06' */
    protected string $companyCode = '';

    /** @var string  ISO 4217 currency code, e.g. 'EGP' */
    protected string $currency = '';

    /** @var string  Financial period YYYYMM, e.g. '202501' */
    protected string $finPeriod = '';

    // -------------------------------------------------------------------------
    // Core respond() method — Requirements 3.1, 3.2, 3.3, 3.4
    // -------------------------------------------------------------------------

    /**
     * Wrap output in the standard API_Response envelope and return a Response.
     *
     * On success (error === null):
     *   success = true, data = $data, error = null          (Req 3.2)
     *
     * On failure (error !== null):
     *   success = false, data = null, error = $error        (Req 3.3)
     *
     * Meta always contains tenant_id, user_id, UTC timestamp, plus the
     * extended fields company_code, currency, fin_period.              (Req 3.4)
     *
     * @param  mixed       $data   Payload on success; ignored on failure.
     * @param  string|null $error  Human-readable error message, or null on success.
     * @param  int         $status HTTP status code (default 200).
     * @return Response
     */
    public function respond(mixed $data, ?string $error = null, int $status = 200): Response
    {
        $success = ($error === null);

        $body = [
            'success' => $success,
            'data'    => $success ? $data : null,   // Req 3.2 / 3.3
            'error'   => $success ? null : $error,  // Req 3.2 / 3.3
            'meta'    => $this->buildMeta(),         // Req 3.4
        ];

        // Apply global serializer for consistent key ordering and monetary formatting (Req 60.1)
        $serializer = new \Core\Serializers\JsonSerializer();
        $formattedBody = json_decode($serializer->serialize($body), true);

        return new Response($formattedBody, $status);
    }

    // -------------------------------------------------------------------------
    // Meta builder — Requirement 3.4
    // -------------------------------------------------------------------------

    /**
     * Build the meta object included in every response.
     *
     * timestamp is always UTC ISO-8601 (e.g. "2025-01-15T10:30:00Z").
     *
     * @return array{
     *   company_code: string,
     *   tenant_id:    string,
     *   user_id:      string,
     *   currency:     string,
     *   fin_period:   string,
     *   timestamp:    string
     * }
     */
    protected function buildMeta(): array
    {
        return [
            'company_code' => $this->companyCode,
            'tenant_id'    => $this->tenantId,
            'user_id'      => $this->userId,
            'currency'     => $this->currency,
            'fin_period'   => $this->finPeriod,
            'timestamp'    => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                                ->format(\DateTimeInterface::ATOM),
        ];
    }

    // -------------------------------------------------------------------------
    // Context setters — called by the front-controller after JWT validation
    // -------------------------------------------------------------------------

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function setCompanyCode(string $companyCode): void
    {
        $this->companyCode = $companyCode;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function setFinPeriod(string $finPeriod): void
    {
        $this->finPeriod = $finPeriod;
    }
}
