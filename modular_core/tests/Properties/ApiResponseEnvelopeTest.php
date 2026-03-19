<?php
/**
 * Property 5: Standard API Response Envelope
 *
 * Validates: Requirements 3.1, 3.2, 3.3, 3.4
 *
 * Properties verified:
 *   P5-a  Every response has exactly the keys: success, data, error, meta.
 *   P5-b  On success (error=null): success=true, data=populated, error=null.
 *   P5-c  On failure (error≠null): success=false, data=null, error=string.
 *   P5-d  meta always contains: company_code, tenant_id, user_id, currency,
 *         fin_period, timestamp (UTC ISO-8601).
 *   P5-e  success and data/error are mutually consistent for any input.
 */

declare(strict_types=1);

namespace Tests\Properties;

use Core\BaseController;
use Core\Response;
use PHPUnit\Framework\TestCase;

class ApiResponseEnvelopeTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function randomUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /** Random company code '01'–'06' */
    private function randomCompanyCode(): string
    {
        return str_pad((string) random_int(1, 6), 2, '0', STR_PAD_LEFT);
    }

    /** Random currency from the 6 supported ones */
    private function randomCurrency(): string
    {
        return ['EGP', 'USD', 'AED', 'SAR', 'EUR', 'GBP'][random_int(0, 5)];
    }

    /** Random fin_period YYYYMM */
    private function randomFinPeriod(): string
    {
        $year  = random_int(2020, 2030);
        $month = str_pad((string) random_int(1, 12), 2, '0', STR_PAD_LEFT);
        return "{$year}{$month}";
    }

    /**
     * Build a concrete BaseController subclass populated with random context.
     * Returns [$controller, $context] where $context holds the values set.
     */
    private function makeController(): array
    {
        $ctx = [
            'tenantId'    => $this->randomUuid(),
            'userId'      => (string) random_int(1, 99999),
            'companyCode' => $this->randomCompanyCode(),
            'currency'    => $this->randomCurrency(),
            'finPeriod'   => $this->randomFinPeriod(),
        ];

        $ctrl = new class extends BaseController {};
        $ctrl->setTenantId($ctx['tenantId']);
        $ctrl->setUserId($ctx['userId']);
        $ctrl->setCompanyCode($ctx['companyCode']);
        $ctrl->setCurrency($ctx['currency']);
        $ctrl->setFinPeriod($ctx['finPeriod']);

        return [$ctrl, $ctx];
    }

    /**
     * Generate a random mixed payload (null, scalar, array, nested array).
     */
    private function randomPayload(): mixed
    {
        return match (random_int(0, 3)) {
            0 => null,
            1 => random_int(1, 10000),
            2 => 'string-' . bin2hex(random_bytes(4)),
            3 => ['id' => random_int(1, 999), 'value' => random_int(0, 100)],
        };
    }

    // -------------------------------------------------------------------------
    // P5-a: Envelope always has exactly the four top-level keys
    // Validates: Requirement 3.1
    // -------------------------------------------------------------------------

    /**
     * **Validates: Requirements 3.1**
     *
     * Property: every response body contains exactly the keys
     * success, data, error, meta — no more, no less.
     */
    public function testEnvelopeAlwaysHasFourTopLevelKeys(): void
    {
        $iterations = 60;

        for ($i = 0; $i < $iterations; $i++) {
            [$ctrl] = $this->makeController();

            // Test both success and failure paths
            $successResponse = $ctrl->respond($this->randomPayload());
            $failureResponse = $ctrl->respond(null, 'Something went wrong ' . $i);

            $expectedKeys = ['success', 'data', 'error', 'meta'];

            foreach ([$successResponse, $failureResponse] as $response) {
                $this->assertInstanceOf(Response::class, $response);
                $body = $response->body;

                $this->assertSame(
                    $expectedKeys,
                    array_keys($body),
                    "Iteration $i: envelope must have exactly keys: " . implode(', ', $expectedKeys)
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // P5-b: Success path — success=true, data=populated, error=null
    // Validates: Requirement 3.2
    // -------------------------------------------------------------------------

    /**
     * **Validates: Requirements 3.2**
     *
     * Property: when respond() is called with no error, success=true,
     * data equals the provided payload, and error is null.
     */
    public function testSuccessResponseShape(): void
    {
        $iterations = 60;

        for ($i = 0; $i < $iterations; $i++) {
            [$ctrl] = $this->makeController();
            $payload = $this->randomPayload();

            $response = $ctrl->respond($payload);
            $body     = $response->body;

            $this->assertTrue(
                $body['success'],
                "Iteration $i: success must be true when no error is provided"
            );
            $this->assertSame(
                $payload,
                $body['data'],
                "Iteration $i: data must equal the provided payload on success"
            );
            $this->assertNull(
                $body['error'],
                "Iteration $i: error must be null on success"
            );
        }
    }

    // -------------------------------------------------------------------------
    // P5-c: Failure path — success=false, data=null, error=string
    // Validates: Requirement 3.3
    // -------------------------------------------------------------------------

    /**
     * **Validates: Requirements 3.3**
     *
     * Property: when respond() is called with a non-null error string,
     * success=false, data=null, and error equals the provided message.
     */
    public function testFailureResponseShape(): void
    {
        $iterations = 60;

        for ($i = 0; $i < $iterations; $i++) {
            [$ctrl] = $this->makeController();
            $errorMsg = 'Error message ' . bin2hex(random_bytes(4));
            $payload  = $this->randomPayload(); // should be ignored

            $response = $ctrl->respond($payload, $errorMsg);
            $body     = $response->body;

            $this->assertFalse(
                $body['success'],
                "Iteration $i: success must be false when error is provided"
            );
            $this->assertNull(
                $body['data'],
                "Iteration $i: data must be null on failure"
            );
            $this->assertSame(
                $errorMsg,
                $body['error'],
                "Iteration $i: error must equal the provided error message"
            );
        }
    }

    // -------------------------------------------------------------------------
    // P5-d: meta always contains all required fields with correct values
    // Validates: Requirement 3.4
    // -------------------------------------------------------------------------

    /**
     * **Validates: Requirements 3.4**
     *
     * Property: meta always contains company_code, tenant_id, user_id,
     * currency, fin_period, and a valid UTC ISO-8601 timestamp.
     */
    public function testMetaContainsAllRequiredFields(): void
    {
        $iterations = 60;

        for ($i = 0; $i < $iterations; $i++) {
            [$ctrl, $ctx] = $this->makeController();

            $response = $ctrl->respond($this->randomPayload());
            $meta     = $response->body['meta'];

            // All required keys present
            $requiredMetaKeys = ['company_code', 'tenant_id', 'user_id', 'currency', 'fin_period', 'timestamp'];
            foreach ($requiredMetaKeys as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $meta,
                    "Iteration $i: meta must contain key '$key'"
                );
            }

            // Values match what was set on the controller
            $this->assertSame($ctx['companyCode'], $meta['company_code'], "Iteration $i: meta.company_code mismatch");
            $this->assertSame($ctx['tenantId'],    $meta['tenant_id'],    "Iteration $i: meta.tenant_id mismatch");
            $this->assertSame($ctx['userId'],      $meta['user_id'],      "Iteration $i: meta.user_id mismatch");
            $this->assertSame($ctx['currency'],    $meta['currency'],     "Iteration $i: meta.currency mismatch");
            $this->assertSame($ctx['finPeriod'],   $meta['fin_period'],   "Iteration $i: meta.fin_period mismatch");

            // timestamp must be a valid UTC ISO-8601 string
            $this->assertIsString($meta['timestamp'], "Iteration $i: meta.timestamp must be a string");
            $parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $meta['timestamp']);
            $this->assertNotFalse(
                $parsed,
                "Iteration $i: meta.timestamp '{$meta['timestamp']}' is not a valid ISO-8601 datetime"
            );
            $tzName = $parsed->getTimezone()->getName();
            $this->assertTrue(
                $tzName === 'UTC' || $tzName === '+00:00',
                "Iteration $i: meta.timestamp must be in UTC (got timezone: {$tzName})"
            );
        }
    }

    // -------------------------------------------------------------------------
    // P5-e: success and data/error are mutually consistent
    // Validates: Requirements 3.1, 3.2, 3.3
    // -------------------------------------------------------------------------

    /**
     * **Validates: Requirements 3.1, 3.2, 3.3**
     *
     * Property: success=true iff error=null, and success=false iff error≠null.
     * Also: data is null iff success=false.
     */
    public function testSuccessAndErrorAreMutuallyConsistent(): void
    {
        $iterations = 80;

        for ($i = 0; $i < $iterations; $i++) {
            [$ctrl] = $this->makeController();

            // Randomly choose success or failure path
            $isSuccess = (bool) random_int(0, 1);
            $payload   = $this->randomPayload();
            $errorMsg  = $isSuccess ? null : ('err-' . bin2hex(random_bytes(3)));

            $response = $ctrl->respond($payload, $errorMsg);
            $body     = $response->body;

            if ($isSuccess) {
                $this->assertTrue($body['success'],  "Iteration $i: success must be true");
                $this->assertNull($body['error'],    "Iteration $i: error must be null on success");
                // data is whatever was passed (including null payloads are valid)
            } else {
                $this->assertFalse($body['success'], "Iteration $i: success must be false");
                $this->assertNotNull($body['error'], "Iteration $i: error must not be null on failure");
                $this->assertNull($body['data'],     "Iteration $i: data must be null on failure");
            }

            // Invariant: success === (error === null)
            $this->assertSame(
                $body['success'],
                $body['error'] === null,
                "Iteration $i: success must equal (error === null)"
            );
        }
    }

    // -------------------------------------------------------------------------
    // P5-f: HTTP status code is preserved on the Response object
    // -------------------------------------------------------------------------

    /**
     * **Validates: Requirements 3.1**
     *
     * Property: the HTTP status code passed to respond() is preserved on the
     * returned Response object.
     */
    public function testHttpStatusCodeIsPreserved(): void
    {
        $iterations = 40;
        $statusCodes = [200, 201, 400, 401, 403, 404, 422, 500];

        for ($i = 0; $i < $iterations; $i++) {
            [$ctrl] = $this->makeController();
            $status = $statusCodes[array_rand($statusCodes)];

            $isError  = $status >= 400;
            $response = $ctrl->respond(
                $isError ? null : ['ok' => true],
                $isError ? 'An error occurred' : null,
                $status
            );

            $this->assertSame(
                $status,
                $response->status,
                "Iteration $i: HTTP status $status must be preserved on Response"
            );
        }
    }
}
