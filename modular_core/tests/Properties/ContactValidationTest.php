<?php
/**
 * Property 7: Contact Requires Email or Phone
 * Property 9: Contact Merge Completeness
 *
 * Validates: Requirements 6.2, 6.7
 */

declare(strict_types=1);

namespace Tests\Properties;

use CRM\Contacts\ContactService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CRM\Contacts\ContactService
 */
class ContactValidationTest extends TestCase
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

    private function randomEmail(): string
    {
        return 'user' . random_int(1000, 9999) . '@example' . random_int(1, 99) . '.com';
    }

    private function randomPhone(): string
    {
        return '+1' . random_int(2000000000, 9999999999);
    }

    /**
     * Build a mock ADOdb connection that tracks inserts and supports
     * configurable duplicate-email responses.
     *
     * @param  bool  $emailExists  Whether the duplicate-email check should find a row
     * @param  int   $newId        ID to return for new inserts
     * @param  array $contacts     Pre-seeded contacts for findById / merge queries
     * @param  array $relatedRows  Pre-seeded activities/deals/notes for merge
     */
    private function buildMockDb(
        bool  $emailExists  = false,
        int   $newId        = 1,
        array $contacts     = [],
        array $relatedRows  = []
    ): object {
        return new class($emailExists, $newId, $contacts, $relatedRows) {
            private bool  $emailExists;
            private int   $newId;
            private array $contacts;
            private array $relatedRows;

            public array  $executedSqls  = [];
            public array  $executedParams = [];
            private int   $affectedRows  = 1;

            public function __construct(
                bool  $emailExists,
                int   $newId,
                array $contacts,
                array $relatedRows
            ) {
                $this->emailExists  = $emailExists;
                $this->newId        = $newId;
                $this->contacts     = $contacts;
                $this->relatedRows  = $relatedRows;
            }

            public function Execute(string $sql, array $params = [])
            {
                $this->executedSqls[]   = $sql;
                $this->executedParams[] = $params;

                $sqlLower = strtolower(trim($sql));

                // Duplicate email check
                if (str_contains($sqlLower, 'select id from contacts') && str_contains($sqlLower, 'email')) {
                    return $this->emailExists ? $this->singleRowRs(['id' => 99]) : $this->emptyRs();
                }

                // INSERT contacts … RETURNING id
                if (str_starts_with($sqlLower, 'insert into contacts')) {
                    return $this->singleRowRs(['id' => $this->newId]);
                }

                // SELECT single contact by id
                if (str_contains($sqlLower, 'from contacts') && str_contains($sqlLower, 'where id = ?')) {
                    $id = $params[0] ?? null;
                    foreach ($this->contacts as $c) {
                        if ((int) $c['id'] === (int) $id
                            && ($c['deleted_at'] ?? null) === null) {
                            return $this->singleRowRs($c);
                        }
                    }
                    return $this->emptyRs();
                }

                // UPDATE contacts SET deleted_at (soft-delete in merge)
                if (str_contains($sqlLower, 'update contacts set deleted_at')) {
                    $id = $params[1] ?? null;
                    foreach ($this->contacts as &$c) {
                        if ((int) $c['id'] === (int) $id) {
                            $c['deleted_at'] = date('Y-m-d H:i:s');
                        }
                    }
                    unset($c);
                    $this->affectedRows = 1;
                    return $this->okRs();
                }

                // UPDATE activities / deals / notes (re-link in merge)
                if (str_contains($sqlLower, 'update activities')
                    || str_contains($sqlLower, 'update deals')
                    || str_contains($sqlLower, 'update notes')) {
                    $survivorId  = $params[0] ?? null;
                    $duplicateId = $params[1] ?? null;
                    foreach ($this->relatedRows as &$row) {
                        if ((int) ($row['contact_id'] ?? 0) === (int) $duplicateId) {
                            $row['contact_id'] = (int) $survivorId;
                        }
                    }
                    unset($row);
                    $this->affectedRows = 1;
                    return $this->okRs();
                }

                return $this->emptyRs();
            }

            public function getRelatedRows(): array { return $this->relatedRows; }
            public function getContacts(): array    { return $this->contacts; }

            public function ErrorMsg(): string { return ''; }
            public function Affected_Rows(): int { return $this->affectedRows; }
            public function Insert_ID(): int { return $this->newId; }
            public function BeginTrans(): void {}
            public function CommitTrans(): void {}
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

    private function makeService(object $db, ?string $tenantId = null): ContactService
    {
        return new ContactService($db, $tenantId ?? $this->randomUuid(), '01');
    }

    // =========================================================================
    // Property 7: Contact Requires Email or Phone
    // Validates: Requirements 6.2
    // =========================================================================

    /**
     * **Validates: Requirements 6.2**
     *
     * Property: for any contact data where both email and phone are absent (null
     * or empty string), ContactService::create() MUST throw an
     * InvalidArgumentException.
     */
    public function testCreateThrowsWhenBothEmailAndPhoneAbsent(): void
    {
        $iterations = 50;

        // Vary the representation of "absent": null, empty string, or missing key
        $emailVariants = [null, '', 'null_key'];
        $phoneVariants = [null, '', 'null_key'];

        for ($i = 0; $i < $iterations; $i++) {
            $db      = $this->buildMockDb(false, $i + 1);
            $service = $this->makeService($db);

            $emailChoice = $emailVariants[array_rand($emailVariants)];
            $phoneChoice = $phoneVariants[array_rand($phoneVariants)];

            $data = ['first_name' => 'Test' . $i, 'last_name' => 'User'];

            if ($emailChoice !== 'null_key') {
                $data['email'] = $emailChoice;
            }
            if ($phoneChoice !== 'null_key') {
                $data['phone'] = $phoneChoice;
            }

            try {
                $service->create($data, 1);
                $this->fail("Iteration $i: create() must throw when both email and phone are absent");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString(
                    'email',
                    strtolower($e->getMessage()),
                    "Iteration $i: exception message must mention email or phone"
                );
            }
        }
    }

    /**
     * **Validates: Requirements 6.2**
     *
     * Property: for any contact data with a valid email present (phone absent),
     * ContactService::create() MUST succeed and return a positive integer ID.
     */
    public function testCreateSucceedsWithEmailOnly(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $newId   = random_int(1, 99999);
            $db      = $this->buildMockDb(false, $newId);
            $service = $this->makeService($db);

            $data = [
                'first_name' => 'Alice' . $i,
                'email'      => $this->randomEmail(),
            ];

            $id = $service->create($data, 1);

            $this->assertIsInt($id, "Iteration $i: create() must return an integer ID");
            $this->assertGreaterThan(0, $id, "Iteration $i: returned ID must be positive");
        }
    }

    /**
     * **Validates: Requirements 6.2**
     *
     * Property: for any contact data with a valid phone present (email absent),
     * ContactService::create() MUST succeed and return a positive integer ID.
     */
    public function testCreateSucceedsWithPhoneOnly(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $newId   = random_int(1, 99999);
            $db      = $this->buildMockDb(false, $newId);
            $service = $this->makeService($db);

            $data = [
                'first_name' => 'Bob' . $i,
                'phone'      => $this->randomPhone(),
            ];

            $id = $service->create($data, 1);

            $this->assertIsInt($id, "Iteration $i: create() must return an integer ID");
            $this->assertGreaterThan(0, $id, "Iteration $i: returned ID must be positive");
        }
    }

    /**
     * **Validates: Requirements 6.2**
     *
     * Property: for any contact data with both email AND phone present,
     * ContactService::create() MUST succeed.
     */
    public function testCreateSucceedsWithBothEmailAndPhone(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $newId   = random_int(1, 99999);
            $db      = $this->buildMockDb(false, $newId);
            $service = $this->makeService($db);

            $data = [
                'first_name' => 'Carol' . $i,
                'email'      => $this->randomEmail(),
                'phone'      => $this->randomPhone(),
            ];

            $id = $service->create($data, 1);

            $this->assertIsInt($id, "Iteration $i: create() must return an integer ID");
            $this->assertGreaterThan(0, $id, "Iteration $i: returned ID must be positive");
        }
    }

    // =========================================================================
    // Property 9: Contact Merge Completeness
    // Validates: Requirements 6.7
    // =========================================================================

    /**
     * **Validates: Requirements 6.7**
     *
     * Property: after merge(survivorId, duplicateId):
     *   1. The duplicate contact must be soft-deleted (deleted_at IS NOT NULL).
     *   2. All activities previously linked to the duplicate must now link to the survivor.
     *   3. All deals previously linked to the duplicate must now link to the survivor.
     *   4. All notes previously linked to the duplicate must now link to the survivor.
     */
    public function testMergeCompleteness(): void
    {
        $iterations = 40;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId    = $this->randomUuid();
            $survivorId  = random_int(1, 500);
            $duplicateId = random_int(501, 1000);

            // Pre-seed contacts
            $contacts = [
                ['id' => $survivorId,  'tenant_id' => $tenantId, 'company_code' => '01',
                 'first_name' => 'Survivor',  'email' => $this->randomEmail(), 'deleted_at' => null],
                ['id' => $duplicateId, 'tenant_id' => $tenantId, 'company_code' => '01',
                 'first_name' => 'Duplicate', 'email' => $this->randomEmail(), 'deleted_at' => null],
            ];

            // Pre-seed related rows linked to the duplicate
            $activityCount = random_int(1, 5);
            $dealCount     = random_int(1, 3);
            $noteCount     = random_int(1, 4);

            $relatedRows = [];
            for ($a = 0; $a < $activityCount; $a++) {
                $relatedRows[] = ['type' => 'activity', 'id' => $a + 1, 'contact_id' => $duplicateId];
            }
            for ($d = 0; $d < $dealCount; $d++) {
                $relatedRows[] = ['type' => 'deal', 'id' => $d + 100, 'contact_id' => $duplicateId];
            }
            for ($n = 0; $n < $noteCount; $n++) {
                $relatedRows[] = ['type' => 'note', 'id' => $n + 200, 'contact_id' => $duplicateId];
            }

            $db      = $this->buildMockDb(false, 1, $contacts, $relatedRows);
            $service = new ContactService($db, $tenantId, '01');

            $service->merge($survivorId, $duplicateId);

            // Assert 1: duplicate must be soft-deleted
            $updatedContacts = $db->getContacts();
            $duplicateRow    = null;
            foreach ($updatedContacts as $c) {
                if ((int) $c['id'] === $duplicateId) {
                    $duplicateRow = $c;
                    break;
                }
            }

            $this->assertNotNull(
                $duplicateRow,
                "Iteration $i: duplicate contact row must still exist (soft-deleted)"
            );
            $this->assertNotNull(
                $duplicateRow['deleted_at'],
                "Iteration $i: duplicate contact must have deleted_at set after merge"
            );

            // Assert 2–4: all related rows must now point to survivor
            $updatedRelated = $db->getRelatedRows();
            foreach ($updatedRelated as $row) {
                $this->assertSame(
                    $survivorId,
                    (int) $row['contact_id'],
                    "Iteration $i: {$row['type']} id={$row['id']} must link to survivor after merge"
                );
            }
        }
    }

    /**
     * **Validates: Requirements 6.7**
     *
     * Property: merge() with survivorId === duplicateId must throw
     * InvalidArgumentException (cannot merge a contact with itself).
     */
    public function testMergeWithSameIdThrows(): void
    {
        $iterations = 30;

        for ($i = 0; $i < $iterations; $i++) {
            $id      = random_int(1, 9999);
            $db      = $this->buildMockDb();
            $service = $this->makeService($db);

            try {
                $service->merge($id, $id);
                $this->fail("Iteration $i: merge() must throw when survivorId === duplicateId");
            } catch (\InvalidArgumentException $e) {
                $this->assertNotEmpty(
                    $e->getMessage(),
                    "Iteration $i: exception message must not be empty"
                );
            }
        }
    }

    /**
     * **Validates: Requirements 6.7**
     *
     * Property: merge() with a non-existent survivor must throw
     * InvalidArgumentException.
     */
    public function testMergeWithNonExistentSurvivorThrows(): void
    {
        $iterations = 20;

        for ($i = 0; $i < $iterations; $i++) {
            $tenantId    = $this->randomUuid();
            $survivorId  = random_int(1, 500);
            $duplicateId = random_int(501, 1000);

            // Only seed the duplicate, not the survivor
            $contacts = [
                ['id' => $duplicateId, 'tenant_id' => $tenantId, 'company_code' => '01',
                 'first_name' => 'Dup', 'email' => $this->randomEmail(), 'deleted_at' => null],
            ];

            $db      = $this->buildMockDb(false, 1, $contacts);
            $service = new ContactService($db, $tenantId, '01');

            try {
                $service->merge($survivorId, $duplicateId);
                $this->fail("Iteration $i: merge() must throw when survivor does not exist");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString(
                    (string) $survivorId,
                    $e->getMessage(),
                    "Iteration $i: exception must mention the missing survivor ID"
                );
            }
        }
    }
}
