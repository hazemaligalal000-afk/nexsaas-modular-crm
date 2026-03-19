<?php
/**
 * CTI/PhoneLookupService.php
 *
 * 3-strategy phone lookup for screen pop: exact → normalized → fuzzy.
 */

declare(strict_types=1);

namespace CTI;

use Core\BaseService;

class PhoneLookupService extends BaseService
{
    private string $tenantId;
    private string $companyCode;

    public function __construct($db, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
    }

    /**
     * Lookup contact by phone using 3-strategy cascade.
     * Returns: ['contact_id' => int|null, 'strategy' => 'exact'|'normalized'|'fuzzy'|'no_match']
     */
    public function lookup(string $rawNumber): array
    {
        $normalized = $this->normalizeAll($rawNumber);

        // Strategy 1: Exact match
        $contact = $this->exactMatch($normalized);
        if ($contact !== null) {
            return ['contact_id' => (int)$contact['id'], 'strategy' => 'exact', 'contact' => $contact];
        }

        // Strategy 2: Normalized match (last 9 digits)
        $contact = $this->normalizedMatch($rawNumber);
        if ($contact !== null) {
            return ['contact_id' => (int)$contact['id'], 'strategy' => 'normalized', 'contact' => $contact];
        }

        // Strategy 3: Fuzzy match (Levenshtein)
        $contact = $this->fuzzyMatch($rawNumber);
        if ($contact !== null) {
            return ['contact_id' => (int)$contact['id'], 'strategy' => 'fuzzy', 'contact' => $contact];
        }

        return ['contact_id' => null, 'strategy' => 'no_match', 'contact' => null];
    }

    private function exactMatch(array $variants): ?array
    {
        $placeholders = implode(',', array_fill(0, count($variants), '?'));
        $params       = array_merge([$this->tenantId, $this->companyCode], $variants);

        $rs = $this->db->Execute(
            "SELECT c.*, comp.name AS company_name FROM contacts c
             LEFT JOIN accounts comp ON comp.id = c.account_id
             WHERE c.tenant_id = ? AND c.company_code = ? AND c.phone IN ({$placeholders})
               AND c.deleted_at IS NULL
             LIMIT 1",
            $params
        );

        return ($rs && !$rs->EOF) ? $rs->fields : null;
    }

    private function normalizedMatch(string $rawNumber): ?array
    {
        $last9 = substr(preg_replace('/\D/', '', $rawNumber), -9);
        if (strlen($last9) < 9) {
            return null;
        }

        $rs = $this->db->Execute(
            "SELECT c.*, comp.name AS company_name FROM contacts c
             LEFT JOIN accounts comp ON comp.id = c.account_id
             WHERE c.tenant_id = ? AND c.company_code = ?
               AND RIGHT(REGEXP_REPLACE(c.phone, '[^0-9]', '', 'g'), 9) = ?
               AND c.deleted_at IS NULL
             LIMIT 1",
            [$this->tenantId, $this->companyCode, $last9]
        );

        return ($rs && !$rs->EOF) ? $rs->fields : null;
    }

    private function fuzzyMatch(string $rawNumber): ?array
    {
        $digits = preg_replace('/\D/', '', $rawNumber);
        if (strlen($digits) < 8) {
            return null;
        }

        $rs = $this->db->Execute(
            "SELECT id, phone, first_name, last_name FROM contacts
             WHERE tenant_id = ? AND company_code = ? AND phone IS NOT NULL
               AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT 5000",
            [$this->tenantId, $this->companyCode]
        );

        $best      = null;
        $bestScore = PHP_INT_MAX;

        while ($rs && !$rs->EOF) {
            $cDigits = preg_replace('/\D/', '', $rs->fields['phone']);
            if (abs(strlen($cDigits) - strlen($digits)) > 2) {
                $rs->MoveNext();
                continue;
            }
            $score = levenshtein(substr($digits, -10), substr($cDigits, -10));
            if ($score < $bestScore && $score <= 2) {
                $bestScore = $score;
                $best      = $rs->fields;
            }
            $rs->MoveNext();
        }

        return $best;
    }

    private function normalizeAll(string $num): array
    {
        $digits   = preg_replace('/\D/', '', $num);
        $variants = [$num];

        if (strlen($digits) >= 10) {
            $variants[] = '+' . $digits;
        }

        // Egypt: +20 → 01X
        if (str_starts_with($digits, '20') && strlen($digits) === 12) {
            $variants[] = '0' . substr($digits, 2);
            $variants[] = substr($digits, 2);
        }

        // Strip leading zeros
        if (str_starts_with($digits, '0')) {
            $variants[] = substr($digits, 1);
        }

        return array_unique($variants);
    }
}
