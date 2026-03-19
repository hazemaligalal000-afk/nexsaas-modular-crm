<?php
/**
 * Property 14: Deal Win Probability Range
 *
 * Validates: Requirements 11.2
 */

declare(strict_types=1);

namespace Tests\Properties;

use CRM\Deals\WinProbabilityService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CRM\Deals\WinProbabilityService
 */
class WinProbabilityTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function randomUuid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Build a mock ADOdb connection that captures the UPDATE parameters.
     */
    private function buildMockDb(): object
    {
        return new class {
            public ?array $lastUpdateParams = null;
            private int   $affectedRows     = 1;

            public function Execute(string $sql, array $params = [])
            {
                $lower = strtolower(trim($sql));

                if (str_starts_with($lower, 'update deals')) {
                    $this->lastUpdateParams = $params;
                    return $this->okRs();
                }

                return $this->emptyRs();
            }

            public function ErrorMsg(): string   { return ''; }
            public function Affected_Rows(): int { return $this->affectedRows; }
            public function Insert_ID(): int     { return 0; }
            public function BeginTrans(): void   {}
            public function CommitTrans(): void  {}
            public function RollbackTrans(): void {}

            private function emptyRs(): object
            {
                return new class {
                    public bool  $EOF    = true;
                    public array $fields = [];
                    public function MoveNext(): void {}
                };
            }

            private function okRs(): object
            {
                return new class {
                    public bool  $EOF    = true;
                    public array $fields = [];
                    public function MoveNext(): void {}
                };
            }
        };
    }

    /**
     * Build a mock RabbitMQ publisher.
     */
    private function buildMockRabbitMQ(): object
    {
        return new class {
            public array $published = [];
            public function publish(string $exchange, string $routingKey, array $payload): void
            {
                $this->published[] = compact('exchange', 'routingKey', 'payload');
            }
        };
    }

    private function makeService(object $db, ?string $tenantId = null): WinProbabilityService
    {
        return new WinProbabilityService(
            $db,
            $this->buildMockRabbitMQ(),
            $tenantId ?? $this->randomUuid(),
            '01'
        );
    }

    // =========================================================================
    // Property 14: Deal Win Probability Range
    // Validates: Requirements 11.2
    // =========================================================================

    /**
     * **Validates: Requirements 11.2**
     *
     * Property: for any probability value passed to applyWinProbability(),
     * the value persisted to the DB MUST be in [0.0, 1.0].
     *
     * This covers:
     *   - Values already in range (pass-through)
     *   - Values below 0.0 (clamped to 0.0)
     *   - Values above 1.0 (clamped to 1.0)
     */
    public function testApplyWinProbabilityPersistsValueInRange(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            // Generate a raw probability that may be out of range
            $rawProbability = (random_int(-200, 200)) / 100.0; // [-2.0, 2.0]

            $dealId  = random_int(1, 9999);
            $db      = $this->buildMockDb();
            $service = $this->makeService($db);

            $service->applyWinProbability($dealId, $rawProbability);

            // The first parameter of the UPDATE call is the persisted probability
            $persisted = $db->lastUpdateParams[0] ?? null;

            $this->assertNotNull(
                $persisted,
                "Iteration $i: UPDATE must have been executed"
            );
            $this->assertIsFloat(
                $persisted,
                "Iteration $i: persisted probability must be a float"
            );
            $this->assertGreaterThanOrEqual(
                0.0,
                $persisted,
                "Iteration $i: persisted probability must be >= 0.0 (raw={$rawProbability})"
            );
            $this->assertLessThanOrEqual(
                1.0,
                $persisted,
                "Iteration $i: persisted probability must be <= 1.0 (raw={$rawProbability})"
            );
        }
    }

    /**
     * **Validates: Requirements 11.2**
     *
     * Property: boundary values 0.0 and 1.0 must be persisted unchanged.
     */
    public function testApplyWinProbabilityBoundaryValues(): void
    {
        foreach ([0.0, 1.0] as $boundary) {
            $dealId  = random_int(1, 9999);
            $db      = $this->buildMockDb();
            $service = $this->makeService($db);

            $service->applyWinProbability($dealId, $boundary);

            $persisted = $db->lastUpdateParams[0] ?? null;

            $this->assertEqualsWithDelta(
                $boundary,
                $persisted,
                0.000001,
                "Boundary probability {$boundary} must be persisted unchanged"
            );
        }
    }

    /**
     * **Validates: Requirements 11.2**
     *
     * Property: values below 0.0 must be clamped to exactly 0.0.
     */
    public function testNegativeProbabilityClampedToZero(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $rawProbability = -(random_int(1, 10000)) / 100.0; // negative

            $dealId  = random_int(1, 9999);
            $db      = $this->buildMockDb();
            $service = $this->makeService($db);

            $service->applyWinProbability($dealId, $rawProbability);

            $persisted = $db->lastUpdateParams[0] ?? null;

            $this->assertEqualsWithDelta(
                0.0,
                $persisted,
                0.000001,
                "Iteration $i: negative probability {$rawProbability} must be clamped to 0.0"
            );
        }
    }

    /**
     * **Validates: Requirements 11.2**
     *
     * Property: values above 1.0 must be clamped to exactly 1.0.
     */
    public function testProbabilityAboveOneClampedToOne(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $rawProbability = 1.0 + (random_int(1, 10000)) / 100.0; // > 1.0

            $dealId  = random_int(1, 9999);
            $db      = $this->buildMockDb();
            $service = $this->makeService($db);

            $service->applyWinProbability($dealId, $rawProbability);

            $persisted = $db->lastUpdateParams[0] ?? null;

            $this->assertEqualsWithDelta(
                1.0,
                $persisted,
                0.000001,
                "Iteration $i: probability {$rawProbability} > 1.0 must be clamped to 1.0"
            );
        }
    }

    // =========================================================================
    // onDealChange — enqueue behaviour
    // =========================================================================

    /**
     * **Validates: Requirements 11.1**
     *
     * Property: onDealChange() MUST publish to 'crm.events' exchange with
     * routing key 'deal.win_probability_request' for all trigger events.
     */
    public function testOnDealChangeEnqueuesForTriggerEvents(): void
    {
        $triggerEvents = [
            'deal.created',
            'deal.stage_changed',
            'deal.value_changed',
            'deal.date_changed',
        ];

        foreach ($triggerEvents as $event) {
            $iterations = 20;
            for ($i = 0; $i < $iterations; $i++) {
                $dealId   = random_int(1, 9999);
                $db       = $this->buildMockDb();
                $rabbitMQ = $this->buildMockRabbitMQ();
                $service  = new WinProbabilityService(
                    $db,
                    $rabbitMQ,
                    $this->randomUuid(),
                    '01'
                );

                $service->onDealChange($dealId, $event);

                $this->assertCount(
                    1,
                    $rabbitMQ->published,
                    "Event '{$event}' iteration $i: exactly one message must be published"
                );

                $msg = $rabbitMQ->published[0];
                $this->assertSame('crm.events', $msg['exchange']);
                $this->assertSame('deal.win_probability_request', $msg['routingKey']);
                $this->assertSame($dealId, $msg['payload']['deal_id']);
                $this->assertSame($event, $msg['payload']['triggered_by']);
            }
        }
    }

    /**
     * **Validates: Requirements 11.1**
     *
     * Property: onDealChange() MUST NOT publish for unrelated events.
     */
    public function testOnDealChangeIgnoresUnrelatedEvents(): void
    {
        $unrelatedEvents = ['deal.deleted', 'contact.created', 'lead.captured', '', 'deal.viewed'];

        foreach ($unrelatedEvents as $event) {
            $db       = $this->buildMockDb();
            $rabbitMQ = $this->buildMockRabbitMQ();
            $service  = new WinProbabilityService(
                $db,
                $rabbitMQ,
                $this->randomUuid(),
                '01'
            );

            $service->onDealChange(1, $event);

            $this->assertEmpty(
                $rabbitMQ->published,
                "Event '{$event}' must NOT trigger a win_probability_request publish"
            );
        }
    }
}
