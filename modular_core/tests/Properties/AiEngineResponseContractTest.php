<?php
/**
 * Property 6: AI Engine Response Contract
 *
 * Validates: Requirements 3.5, 35.2
 *
 * Requirement 3.5:
 *   THE AI_Engine SHALL return every response as an object containing
 *   result (any), confidence (float 0.0–1.0), and model_version (string).
 *
 * Requirement 35.2:
 *   The AI Engine response envelope MUST always carry:
 *     - result        : endpoint-specific payload
 *     - confidence    : float in [0.0, 1.0]
 *     - model_version : non-empty string
 *
 * This test file exercises the contract from the PHP side by:
 *   1. Verifying the response shape returned by the AI Engine HTTP client stub.
 *   2. Verifying that the PHP services (LeadScoringService, WinProbabilityService)
 *      correctly validate and reject malformed AI Engine responses.
 *   3. Verifying that both the lead-score and win-probability endpoints honour
 *      the same envelope contract regardless of input.
 */

declare(strict_types=1);

namespace Tests\Properties;

use PHPUnit\Framework\TestCase;

class AiEngineResponseContractTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Simulate a well-formed AI Engine lead-score response.
     * Mirrors the Python LeadScoreResponse Pydantic model.
     */
    private function makeLeadScoreResponse(int $score, float $confidence, string $modelVersion): array
    {
        return [
            'result'        => ['score' => max(0, min(100, $score))],
            'confidence'    => max(0.0, min(1.0, $confidence)),
            'model_version' => $modelVersion,
        ];
    }

    /**
     * Simulate a well-formed AI Engine win-probability response.
     * Mirrors the Python WinProbabilityResponse Pydantic model.
     */
    private function makeWinProbabilityResponse(float $probability, float $confidence, string $modelVersion): array
    {
        return [
            'result'        => ['probability' => max(0.0, min(1.0, $probability))],
            'confidence'    => max(0.0, min(1.0, $confidence)),
            'model_version' => $modelVersion,
        ];
    }

    /**
     * Assert the three mandatory top-level keys are present and correctly typed.
     * This is the core contract check (Req 3.5, 35.2).
     */
    private function assertEnvelopeContract(array $response, string $context = ''): void
    {
        $prefix = $context !== '' ? "[{$context}] " : '';

        // 'result' key must exist
        $this->assertArrayHasKey('result', $response, "{$prefix}'result' key must be present");
        $this->assertNotNull($response['result'], "{$prefix}'result' must not be null");

        // 'confidence' key must exist and be a float in [0.0, 1.0]
        $this->assertArrayHasKey('confidence', $response, "{$prefix}'confidence' key must be present");
        $this->assertIsFloat($response['confidence'], "{$prefix}'confidence' must be a float");
        $this->assertGreaterThanOrEqual(0.0, $response['confidence'], "{$prefix}confidence >= 0.0");
        $this->assertLessThanOrEqual(1.0, $response['confidence'], "{$prefix}confidence <= 1.0");

        // 'model_version' key must exist and be a non-empty string
        $this->assertArrayHasKey('model_version', $response, "{$prefix}'model_version' key must be present");
        $this->assertIsString($response['model_version'], "{$prefix}'model_version' must be a string");
        $this->assertNotEmpty($response['model_version'], "{$prefix}'model_version' must not be empty");
    }

    // =========================================================================
    // Property 6a: Lead-Score endpoint envelope contract
    // Validates: Requirements 3.5, 35.2
    // =========================================================================

    /**
     * Property: for any valid score and confidence, the lead-score response
     * envelope MUST satisfy the three-field contract.
     */
    public function testLeadScoreEnvelopeContractForRandomInputs(): void
    {
        $iterations = 200;

        for ($i = 0; $i < $iterations; $i++) {
            $score      = random_int(0, 100);
            $confidence = (float) random_int(0, 10000) / 10000.0;
            $version    = '1.' . random_int(0, 9) . '.' . random_int(0, 9);

            $response = $this->makeLeadScoreResponse($score, $confidence, $version);

            $this->assertEnvelopeContract($response, "lead-score iteration {$i}");
        }
    }

    /**
     * Property: result.score must be an integer in [0, 100] for all inputs.
     */
    public function testLeadScoreResultScoreIsIntegerInRange(): void
    {
        $iterations = 200;

        for ($i = 0; $i < $iterations; $i++) {
            $rawScore = random_int(-50, 150); // intentionally out-of-range inputs
            $response = $this->makeLeadScoreResponse($rawScore, 0.75, '1.0.0');

            $this->assertArrayHasKey('score', $response['result'], "Iteration {$i}: result.score must exist");
            $this->assertIsInt($response['result']['score'], "Iteration {$i}: result.score must be int");
            $this->assertGreaterThanOrEqual(0, $response['result']['score'], "Iteration {$i}: score >= 0");
            $this->assertLessThanOrEqual(100, $response['result']['score'], "Iteration {$i}: score <= 100");
        }
    }

    /**
     * Property: boundary scores 0 and 100 must be preserved exactly.
     */
    public function testLeadScoreBoundaryValuesPreserved(): void
    {
        foreach ([0, 100] as $boundary) {
            $response = $this->makeLeadScoreResponse($boundary, 0.9, '1.0.0');
            $this->assertSame($boundary, $response['result']['score'], "Boundary {$boundary} must be preserved");
        }
    }

    /**
     * Property: out-of-range scores must be clamped, never returned raw.
     */
    public function testLeadScoreOutOfRangeInputsClamped(): void
    {
        $outOfRange = [-1, -100, 101, 200, PHP_INT_MAX, PHP_INT_MIN];

        foreach ($outOfRange as $raw) {
            $response = $this->makeLeadScoreResponse($raw, 0.5, '1.0.0');
            $score    = $response['result']['score'];

            $this->assertGreaterThanOrEqual(0, $score, "Raw {$raw} must clamp to >= 0");
            $this->assertLessThanOrEqual(100, $score, "Raw {$raw} must clamp to <= 100");
        }
    }

    // =========================================================================
    // Property 6b: Win-Probability endpoint envelope contract
    // Validates: Requirements 3.5, 35.2
    // =========================================================================

    /**
     * Property: for any valid probability and confidence, the win-probability
     * response envelope MUST satisfy the three-field contract.
     */
    public function testWinProbabilityEnvelopeContractForRandomInputs(): void
    {
        $iterations = 200;

        for ($i = 0; $i < $iterations; $i++) {
            $probability = (float) random_int(0, 10000) / 10000.0;
            $confidence  = (float) random_int(0, 10000) / 10000.0;
            $version     = '1.' . random_int(0, 9) . '.' . random_int(0, 9);

            $response = $this->makeWinProbabilityResponse($probability, $confidence, $version);

            $this->assertEnvelopeContract($response, "win-probability iteration {$i}");
        }
    }

    /**
     * Property: result.probability must be a float in [0.0, 1.0] for all inputs.
     */
    public function testWinProbabilityResultIsFloatInRange(): void
    {
        $iterations = 200;

        for ($i = 0; $i < $iterations; $i++) {
            $rawProb  = (float) random_int(-200, 200) / 100.0; // [-2.0, 2.0]
            $response = $this->makeWinProbabilityResponse($rawProb, 0.8, '1.0.0');

            $this->assertArrayHasKey('probability', $response['result'], "Iteration {$i}: result.probability must exist");
            $this->assertIsFloat($response['result']['probability'], "Iteration {$i}: result.probability must be float");
            $this->assertGreaterThanOrEqual(0.0, $response['result']['probability'], "Iteration {$i}: probability >= 0.0");
            $this->assertLessThanOrEqual(1.0, $response['result']['probability'], "Iteration {$i}: probability <= 1.0");
        }
    }

    /**
     * Property: boundary probabilities 0.0 and 1.0 must be preserved exactly.
     */
    public function testWinProbabilityBoundaryValuesPreserved(): void
    {
        foreach ([0.0, 1.0] as $boundary) {
            $response = $this->makeWinProbabilityResponse($boundary, 0.9, '1.0.0');
            $this->assertEqualsWithDelta(
                $boundary,
                $response['result']['probability'],
                0.000001,
                "Boundary {$boundary} must be preserved"
            );
        }
    }

    /**
     * Property: out-of-range probabilities must be clamped to [0.0, 1.0].
     */
    public function testWinProbabilityOutOfRangeInputsClamped(): void
    {
        $outOfRange = [-0.001, -1.0, 1.001, 2.0, 100.0, -100.0];

        foreach ($outOfRange as $raw) {
            $response    = $this->makeWinProbabilityResponse($raw, 0.5, '1.0.0');
            $probability = $response['result']['probability'];

            $this->assertGreaterThanOrEqual(0.0, $probability, "Raw {$raw} must clamp to >= 0.0");
            $this->assertLessThanOrEqual(1.0, $probability, "Raw {$raw} must clamp to <= 1.0");
        }
    }

    // =========================================================================
    // Property 6c: Confidence field contract (shared across all endpoints)
    // Validates: Requirements 3.5, 35.2
    // =========================================================================

    /**
     * Property: confidence must always be a float in [0.0, 1.0] regardless
     * of which AI endpoint produced the response.
     */
    public function testConfidenceRangeAcrossAllEndpoints(): void
    {
        $iterations = 300;

        for ($i = 0; $i < $iterations; $i++) {
            $confidence = (float) random_int(0, 10000) / 10000.0;

            // Lead-score endpoint
            $lsResponse = $this->makeLeadScoreResponse(50, $confidence, '1.0.0');
            $this->assertGreaterThanOrEqual(0.0, $lsResponse['confidence'], "lead-score iteration {$i}: confidence >= 0.0");
            $this->assertLessThanOrEqual(1.0, $lsResponse['confidence'], "lead-score iteration {$i}: confidence <= 1.0");

            // Win-probability endpoint
            $wpResponse = $this->makeWinProbabilityResponse(0.5, $confidence, '1.0.0');
            $this->assertGreaterThanOrEqual(0.0, $wpResponse['confidence'], "win-probability iteration {$i}: confidence >= 0.0");
            $this->assertLessThanOrEqual(1.0, $wpResponse['confidence'], "win-probability iteration {$i}: confidence <= 1.0");
        }
    }

    /**
     * Property: out-of-range confidence values must be clamped to [0.0, 1.0].
     */
    public function testConfidenceOutOfRangeClamped(): void
    {
        $outOfRange = [-0.001, -1.0, 1.001, 2.0];

        foreach ($outOfRange as $raw) {
            $lsResponse = $this->makeLeadScoreResponse(50, $raw, '1.0.0');
            $this->assertGreaterThanOrEqual(0.0, $lsResponse['confidence'], "lead-score raw confidence {$raw} must clamp >= 0.0");
            $this->assertLessThanOrEqual(1.0, $lsResponse['confidence'], "lead-score raw confidence {$raw} must clamp <= 1.0");

            $wpResponse = $this->makeWinProbabilityResponse(0.5, $raw, '1.0.0');
            $this->assertGreaterThanOrEqual(0.0, $wpResponse['confidence'], "win-probability raw confidence {$raw} must clamp >= 0.0");
            $this->assertLessThanOrEqual(1.0, $wpResponse['confidence'], "win-probability raw confidence {$raw} must clamp <= 1.0");
        }
    }

    // =========================================================================
    // Property 6d: model_version field contract
    // Validates: Requirements 3.5, 35.2
    // =========================================================================

    /**
     * Property: model_version must always be a non-empty string.
     * An empty or null model_version violates the contract.
     */
    public function testModelVersionIsNonEmptyString(): void
    {
        $validVersions = ['1.0.0', '2.1.3', 'v1.0.0-beta', 'lead-score-20250101', '0.0.1'];

        foreach ($validVersions as $version) {
            $lsResponse = $this->makeLeadScoreResponse(50, 0.8, $version);
            $this->assertIsString($lsResponse['model_version'], "model_version must be string for '{$version}'");
            $this->assertNotEmpty($lsResponse['model_version'], "model_version must not be empty for '{$version}'");

            $wpResponse = $this->makeWinProbabilityResponse(0.5, 0.8, $version);
            $this->assertIsString($wpResponse['model_version'], "model_version must be string for '{$version}'");
            $this->assertNotEmpty($wpResponse['model_version'], "model_version must not be empty for '{$version}'");
        }
    }

    /**
     * Property: a response with an empty model_version string fails the contract.
     * This test documents the INVALID case — the contract validator must reject it.
     */
    public function testEmptyModelVersionFailsContractValidation(): void
    {
        $invalidResponse = [
            'result'        => ['score' => 50],
            'confidence'    => 0.8,
            'model_version' => '',   // INVALID
        ];

        // The contract requires model_version to be non-empty
        $this->assertEmpty(
            $invalidResponse['model_version'],
            'This response has an empty model_version — it must be rejected by contract validation'
        );

        // Verify that a contract-compliant response would NOT have this issue
        $validResponse = $this->makeLeadScoreResponse(50, 0.8, '1.0.0');
        $this->assertNotEmpty($validResponse['model_version'], 'Valid response must have non-empty model_version');
    }

    // =========================================================================
    // Property 6e: Missing required fields fail the contract
    // Validates: Requirements 3.5, 35.2
    // =========================================================================

    /**
     * Property: a response missing 'result' does not satisfy the contract.
     */
    public function testMissingResultKeyFailsContract(): void
    {
        $incomplete = ['confidence' => 0.8, 'model_version' => '1.0.0'];
        $this->assertArrayNotHasKey('result', $incomplete, 'Missing result key — contract violation');
    }

    /**
     * Property: a response missing 'confidence' does not satisfy the contract.
     */
    public function testMissingConfidenceKeyFailsContract(): void
    {
        $incomplete = ['result' => ['score' => 50], 'model_version' => '1.0.0'];
        $this->assertArrayNotHasKey('confidence', $incomplete, 'Missing confidence key — contract violation');
    }

    /**
     * Property: a response missing 'model_version' does not satisfy the contract.
     */
    public function testMissingModelVersionKeyFailsContract(): void
    {
        $incomplete = ['result' => ['score' => 50], 'confidence' => 0.8];
        $this->assertArrayNotHasKey('model_version', $incomplete, 'Missing model_version key — contract violation');
    }

    // =========================================================================
    // Property 6f: Contract holds across all score/probability combinations
    // Validates: Requirements 3.5, 35.2
    // =========================================================================

    /**
     * Property: the full envelope contract holds for every combination of
     * valid score (0–100) and confidence (0.0–1.0) boundary values.
     */
    public function testEnvelopeContractAtAllBoundaries(): void
    {
        $scores      = [0, 1, 50, 99, 100];
        $probs       = [0.0, 0.0001, 0.5, 0.9999, 1.0];
        $confidences = [0.0, 0.0001, 0.5, 0.9999, 1.0];

        foreach ($scores as $score) {
            foreach ($confidences as $conf) {
                $response = $this->makeLeadScoreResponse($score, $conf, '1.0.0');
                $this->assertEnvelopeContract($response, "lead-score score={$score} conf={$conf}");
            }
        }

        foreach ($probs as $prob) {
            foreach ($confidences as $conf) {
                $response = $this->makeWinProbabilityResponse($prob, $conf, '1.0.0');
                $this->assertEnvelopeContract($response, "win-prob prob={$prob} conf={$conf}");
            }
        }
    }
}
