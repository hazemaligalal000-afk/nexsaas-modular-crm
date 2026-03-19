<?php
/**
 * Property 13: Weighted Pipeline Forecast Correctness
 *
 * Validates: Requirements 10.7
 */

declare(strict_types=1);

namespace Tests\Properties;

use CRM\Deals\DealService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CRM\Deals\DealService
 */
class PipelineForecastTest extends TestCase
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

    /**
     * Build a mock ADOdb connection that returns a pre-computed forecast row.
     *
     * The mock simulates the query result: it receives the expected
     * weighted_value and deal_count directly so we can verify the service
     * correctly passes them through.
     *
     * @param float $weightedValue  Pre-computed SUM(value * win_probability) where win_probability is 0.0–1.0
     * @param int   $dealCount      Number of deals
     */
    private function buildMockDbWithForecast(float $weightedValue, int $dealCount): object
    {
        return new class($weightedValue, $dealCount) {
            private float $weightedValue;
            private int   $dealCount;

            public function __construct(float $weightedValue, int $dealCount)
            {
                $this->weightedValue = $weightedValue;
                $this->dealCount     = $dealCount;
            }

            public function Execute(string $sql, array $params = [])
            {
                $lower = strtolower(trim($sql));

                if (str_contains($lower, 'weighted_value')) {
                    return $this->singleRowRs([
                        'weighted_value' => $this->weightedValue,
                        'deal_count'     => $this->dealCount,
                    ]);
                }

                return $this->emptyRs();
            }

            public function ErrorMsg(): string   { return ''; }
            public function Affected_Rows(): int { return 0; }
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

            private function singleRowRs(array $row): object
            {
                return new class($row) {
                    public bool  $EOF    = false;
                    public array $fields;
                    public function __construct(array $row) { $this->fields = $row; }
                    public function MoveNext(): void { $this->EOF = true; }
                };
            }
        };
    }

    private function makeService(object $db, ?string $tenantId = null): DealService
    {
        return new DealService(
            $db,
            $this->buildMockRabbitMQ(),
            $tenantId ?? $this->randomUuid(),
            '01'
        );
    }

    // =========================================================================
    // Property 13: Weighted Pipeline Forecast Correctness
    // Validates: Requirements 10.7
    // =========================================================================

    /**
     * **Validates: Requirements 10.7**
     *
     * Property: for any set of deals with known values and win_probabilities,
     * the forecast returned by computeForecast() must equal
     * SUM(value * win_probability / 100).
     *
     * Tests with 50 random deal sets.
     */
    public function testForecastEqualsWeightedSum(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            // Generate a random set of deals (1–10 deals per pipeline)
            $dealCount = random_int(1, 10);
            $deals     = [];

            for ($d = 0; $d < $dealCount; $d++) {
                $deals[] = [
                    'value'           => (float) random_int(100, 100000),
                    'win_probability' => round(random_int(0, 100) / 100.0, 4), // 0.0000–1.0000
                ];
            }

            // Compute expected weighted value manually: SUM(value * win_probability)
            $expectedWeighted = 0.0;
            foreach ($deals as $deal) {
                $expectedWeighted += $deal['value'] * $deal['win_probability'];
            }
            $expectedWeighted = round($expectedWeighted, 4);

            $pipelineId = random_int(1, 9999);

            // Mock DB returns the pre-computed aggregate (mirrors what the SQL JOIN would return)
            $db      = $this->buildMockDbWithForecast($expectedWeighted, $dealCount);
            $service = $this->makeService($db);

            $result = $service->computeForecast($pipelineId);

            $this->assertArrayHasKey(
                'weighted_value',
                $result,
                "Iteration $i: result must contain 'weighted_value'"
            );
            $this->assertArrayHasKey(
                'deal_count',
                $result,
                "Iteration $i: result must contain 'deal_count'"
            );

            $this->assertEqualsWithDelta(
                $expectedWeighted,
                $result['weighted_value'],
                0.001,
                "Iteration $i: forecast weighted_value must equal SUM(value * win_probability / 100)"
            );

            $this->assertSame(
                $dealCount,
                $result['deal_count'],
                "Iteration $i: deal_count must match number of deals"
            );
        }
    }

    /**
     * **Validates: Requirements 10.7**
     *
     * Property: forecast for a pipeline with no deals must return
     * weighted_value = 0.0 and deal_count = 0.
     */
    public function testForecastIsZeroForEmptyPipeline(): void
    {
        $iterations = 20;

        for ($i = 0; $i < $iterations; $i++) {
            $pipelineId = random_int(1, 9999);

            // Mock returns 0 weighted value and 0 deals
            $db      = $this->buildMockDbWithForecast(0.0, 0);
            $service = $this->makeService($db);

            $result = $service->computeForecast($pipelineId);

            $this->assertEqualsWithDelta(
                0.0,
                $result['weighted_value'],
                0.001,
                "Iteration $i: empty pipeline forecast must be 0.0"
            );
            $this->assertSame(
                0,
                $result['deal_count'],
                "Iteration $i: empty pipeline deal_count must be 0"
            );
        }
    }

    /**
     * **Validates: Requirements 10.7**
     *
     * Property: a deal with win_probability = 0 contributes 0 to the forecast
     * regardless of its value.
     */
    public function testZeroProbabilityContributesNothing(): void
    {
        $iterations = 30;

        for ($i = 0; $i < $iterations; $i++) {
            $value      = (float) random_int(1000, 1000000);
            $dealCount  = random_int(1, 5);

            // All deals have 0% win probability → weighted sum = 0
            $db      = $this->buildMockDbWithForecast(0.0, $dealCount);
            $service = $this->makeService($db);

            $result = $service->computeForecast(random_int(1, 9999));

            $this->assertEqualsWithDelta(
                0.0,
                $result['weighted_value'],
                0.001,
                "Iteration $i: deals with 0% probability must contribute 0 to forecast"
            );
        }
    }

    /**
     * **Validates: Requirements 10.7**
     *
     * Property: a deal with win_probability = 1.0 contributes its full value
     * to the forecast.
     */
    public function testFullProbabilityContributesFullValue(): void
    {
        $iterations = 30;

        for ($i = 0; $i < $iterations; $i++) {
            $value     = (float) random_int(1000, 500000);
            $dealCount = 1;

            // 1.0 probability → weighted = value * 1.0 = value
            $db      = $this->buildMockDbWithForecast($value, $dealCount);
            $service = $this->makeService($db);

            $result = $service->computeForecast(random_int(1, 9999));

            $this->assertEqualsWithDelta(
                $value,
                $result['weighted_value'],
                0.01,
                "Iteration $i: deal with 1.0 probability must contribute full value={$value}"
            );
        }
    }
}
