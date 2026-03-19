<?php
/**
 * CRM/Leads/LeadFormBuilder.php
 * Requirements: 7.2, 7.3
 *
 * Generates a self-contained, embeddable HTML lead capture form with CSRF
 * protection. The form POSTs to /api/v1/crm/leads/capture (public endpoint).
 *
 * Usage:
 *   $builder = new LeadFormBuilder();
 *   $html    = $builder->generate([
 *       'tenant_id'    => 'uuid-here',
 *       'company_code' => '01',
 *       'fields'       => ['full_name', 'email', 'phone', 'company', 'source'],
 *       'action'       => '/api/v1/crm/leads/capture',  // optional override
 *       'submit_label' => 'Get in Touch',               // optional
 *       'custom_fields'=> [                             // optional extra fields
 *           ['name' => 'job_title', 'label' => 'Job Title', 'type' => 'text'],
 *       ],
 *   ]);
 */
declare(strict_types=1);

namespace CRM\Leads;

class LeadFormBuilder
{
    /** Default endpoint for form submissions */
    private const DEFAULT_ACTION = '/api/v1/crm/leads/capture';

    /** Standard fields supported out of the box */
    private const STANDARD_FIELDS = [
        'full_name' => [
            'label'    => 'Full Name',
            'type'     => 'text',
            'required' => true,
            'max'      => 255,
        ],
        'email' => [
            'label'    => 'Email',
            'type'     => 'email',
            'required' => false,
            'max'      => 255,
        ],
        'phone' => [
            'label'    => 'Phone',
            'type'     => 'tel',
            'required' => false,
            'max'      => 50,
        ],
        'company' => [
            'label'    => 'Company',
            'type'     => 'text',
            'required' => false,
            'max'      => 255,
        ],
        'source' => [
            'label'    => 'How did you hear about us?',
            'type'     => 'text',
            'required' => false,
            'max'      => 50,
        ],
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate a self-contained, embeddable HTML lead capture form.
     *
     * Config keys:
     *   tenant_id     (string, required) — tenant UUID embedded as hidden field
     *   company_code  (string, required) — company code embedded as hidden field
     *   fields        (string[], optional) — subset of standard fields to render;
     *                 defaults to ['full_name', 'email', 'phone']
     *   action        (string, optional) — form action URL; defaults to DEFAULT_ACTION
     *   submit_label  (string, optional) — submit button text; defaults to 'Submit'
     *   custom_fields (array[], optional) — extra fields: each entry must have
     *                 'name' (string), 'label' (string), 'type' (string);
     *                 optionally 'required' (bool) and 'max' (int)
     *   form_id       (string, optional) — HTML id attribute for the <form> element
     *   css_class     (string, optional) — extra CSS class(es) for the <form> element
     *
     * A CSRF token is generated per call and stored in $_SESSION['csrf_lead_capture'].
     *
     * @param  array $config  Form configuration (see above).
     * @return string         Self-contained HTML fragment ready for embedding.
     *
     * @throws \InvalidArgumentException if tenant_id or company_code is missing.
     */
    public function generate(array $config): string
    {
        $tenantId    = trim((string) ($config['tenant_id']    ?? ''));
        $companyCode = trim((string) ($config['company_code'] ?? ''));

        if ($tenantId === '') {
            throw new \InvalidArgumentException('LeadFormBuilder::generate requires tenant_id.');
        }
        if ($companyCode === '') {
            throw new \InvalidArgumentException('LeadFormBuilder::generate requires company_code.');
        }

        // Generate and store CSRF token
        $csrfToken = $this->generateCsrfToken();

        // Resolve config
        $action      = trim((string) ($config['action']       ?? self::DEFAULT_ACTION));
        $submitLabel = htmlspecialchars(
            (string) ($config['submit_label'] ?? 'Submit'),
            ENT_QUOTES, 'UTF-8'
        );
        $formId    = htmlspecialchars((string) ($config['form_id']   ?? 'nexsaas-lead-form'), ENT_QUOTES, 'UTF-8');
        $cssClass  = htmlspecialchars((string) ($config['css_class'] ?? ''), ENT_QUOTES, 'UTF-8');
        $fields    = $config['fields'] ?? ['full_name', 'email', 'phone'];
        $customFields = $config['custom_fields'] ?? [];

        // Escape hidden field values
        $eTenantId    = htmlspecialchars($tenantId,    ENT_QUOTES, 'UTF-8');
        $eCompanyCode = htmlspecialchars($companyCode, ENT_QUOTES, 'UTF-8');
        $eCsrfToken   = htmlspecialchars($csrfToken,   ENT_QUOTES, 'UTF-8');
        $eAction      = htmlspecialchars($action,      ENT_QUOTES, 'UTF-8');
        $eClass       = $cssClass !== '' ? ' ' . $cssClass : '';

        // Build field HTML
        $fieldsHtml = $this->renderStandardFields($fields);
        $fieldsHtml .= $this->renderCustomFields($customFields);

        return <<<HTML
<form id="{$formId}" method="POST" action="{$eAction}" class="nexsaas-lead-form{$eClass}">
    <input type="hidden" name="tenant_id"    value="{$eTenantId}">
    <input type="hidden" name="company_code" value="{$eCompanyCode}">
    <input type="hidden" name="csrf_token"   value="{$eCsrfToken}">
    <input type="hidden" name="source"       value="web_form">
{$fieldsHtml}
    <div class="nexsaas-form-group nexsaas-form-submit">
        <button type="submit">{$submitLabel}</button>
    </div>
</form>
HTML;
    }

    /**
     * Generate an embeddable HTML form (legacy alias kept for backward compat).
     *
     * @deprecated Use generate(array $config) instead.
     */
    public function generateForm(string $tenantId, string $companyCode): string
    {
        return $this->generate([
            'tenant_id'    => $tenantId,
            'company_code' => $companyCode,
        ]);
    }

    /**
     * Validate a submitted CSRF token against the stored session token.
     *
     * Uses hash_equals() to prevent timing attacks.
     *
     * @param  string $token        Token from the form submission.
     * @param  string $sessionToken Token stored in the session.
     * @return bool
     */
    public function validateCsrf(string $token, string $sessionToken): bool
    {
        if ($token === '' || $sessionToken === '') {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a cryptographically random CSRF token and store it in the session.
     *
     * @return string  Hex-encoded 32-byte token.
     */
    private function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_lead_capture'] = $token;
        return $token;
    }

    /**
     * Render HTML for the requested subset of standard fields.
     *
     * @param  string[] $fields  Field names from STANDARD_FIELDS keys.
     * @return string
     */
    private function renderStandardFields(array $fields): string
    {
        $html = '';
        foreach ($fields as $fieldName) {
            $fieldName = (string) $fieldName;

            // Skip 'source' — it is always sent as a hidden web_form value
            if ($fieldName === 'source') {
                continue;
            }

            if (!isset(self::STANDARD_FIELDS[$fieldName])) {
                continue;
            }

            $def      = self::STANDARD_FIELDS[$fieldName];
            $id       = 'nexsaas_' . $fieldName;
            $label    = htmlspecialchars($def['label'], ENT_QUOTES, 'UTF-8');
            $type     = htmlspecialchars($def['type'],  ENT_QUOTES, 'UTF-8');
            $name     = htmlspecialchars($fieldName,    ENT_QUOTES, 'UTF-8');
            $max      = (int) $def['max'];
            $required = $def['required'] ? ' required' : '';

            $html .= <<<HTML
    <div class="nexsaas-form-group">
        <label for="{$id}">{$label}</label>
        <input type="{$type}" id="{$id}" name="{$name}" maxlength="{$max}"{$required}>
    </div>

HTML;
        }
        return $html;
    }

    /**
     * Render HTML for caller-supplied custom fields.
     *
     * Each entry must have: name (string), label (string), type (string).
     * Optional: required (bool), max (int).
     *
     * @param  array[] $customFields
     * @return string
     */
    private function renderCustomFields(array $customFields): string
    {
        $html = '';
        foreach ($customFields as $field) {
            if (!isset($field['name'], $field['label'], $field['type'])) {
                continue;
            }

            $name     = htmlspecialchars((string) $field['name'],  ENT_QUOTES, 'UTF-8');
            $label    = htmlspecialchars((string) $field['label'], ENT_QUOTES, 'UTF-8');
            $type     = htmlspecialchars((string) $field['type'],  ENT_QUOTES, 'UTF-8');
            $id       = 'nexsaas_custom_' . $name;
            $max      = isset($field['max']) ? (int) $field['max'] : 255;
            $required = !empty($field['required']) ? ' required' : '';

            $html .= <<<HTML
    <div class="nexsaas-form-group">
        <label for="{$id}">{$label}</label>
        <input type="{$type}" id="{$id}" name="{$name}" maxlength="{$max}"{$required}>
    </div>

HTML;
        }
        return $html;
    }
}
