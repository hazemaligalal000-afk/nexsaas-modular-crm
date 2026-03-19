<?php
/**
 * EmailDesigner/EmailSendService.php
 *
 * Email send pipeline: merge fields → compile MJML → track → queue.
 */

declare(strict_types=1);

namespace EmailDesigner;

use Core\BaseService;

class EmailSendService extends BaseService
{
    private MergeFieldsEngine $merger;
    private BrandSettingsModel $brand;
    private string $tenantId;
    private string $companyCode;
    private $queue;

    public function __construct($db, $queue, string $tenantId, string $companyCode)
    {
        parent::__construct($db);
        $this->merger      = new MergeFieldsEngine();
        $this->brand       = new BrandSettingsModel($db);
        $this->tenantId    = $tenantId;
        $this->companyCode = $companyCode;
        $this->queue       = $queue;
    }

    /**
     * Send single email (triggered from workflow or manual).
     */
    public function sendSingle(
        int    $templateId,
        int    $contactId,
        array  $extraContext = [],
        string $companyCode = '01'
    ): int {
        $template  = $this->loadTemplate($templateId);
        $contact   = $this->loadContact($contactId);
        $brandData = $this->brand->getForCompany($this->tenantId, $companyCode);

        // Build merge context
        $context = array_merge([
            'contact' => $contact,
            'company' => $brandData,
            'system'  => ['date' => date('Y-m-d'), 'year' => date('Y')],
        ], $extraContext);

        // Merge fields in subject & body
        $subject   = $this->merger->render($template['subject_en'], $context);
        $preheader = $this->merger->render($template['preheader_en'] ?? '', $context);

        // Compile HTML (simplified — in production use MJML service)
        $html = $this->compileHTML($template['html_compiled'], $context);

        // Create send record
        $sendId = $this->createSendRecord([
            'tenant_id'     => $this->tenantId,
            'company_code'  => $companyCode,
            'template_id'   => $templateId,
            'contact_id'    => $contactId,
            'to_email'      => $contact['email'],
            'to_name'       => $contact['first_name'] . ' ' . $contact['last_name'],
            'from_name'     => $brandData['sender_name_en'],
            'from_email'    => $brandData['sender_email'],
            'reply_to'      => $brandData['reply_to_email'],
            'subject'       => $subject,
            'preheader'     => $preheader,
            'html_body'     => $html,
            'merge_context' => json_encode($context),
            'status'        => 'queued',
        ]);

        // Queue for sending
        $this->queue->publish('email.send.single', [
            'send_id'   => $sendId,
            'tenant_id' => $this->tenantId,
            'priority'  => 'normal',
        ]);

        return $sendId;
    }

    private function loadTemplate(int $id): array
    {
        $rs = $this->db->Execute(
            'SELECT * FROM email_templates WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$id, $this->tenantId]
        );
        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException("Template {$id} not found");
        }
        return $rs->fields;
    }

    private function loadContact(int $id): array
    {
        $rs = $this->db->Execute(
            'SELECT * FROM contacts WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$id, $this->tenantId]
        );
        if ($rs === false || $rs->EOF) {
            throw new \RuntimeException("Contact {$id} not found");
        }
        return $rs->fields;
    }

    private function compileHTML(string $template, array $context): string
    {
        // Simplified — in production call MJML microservice
        return $this->merger->render($template, $context);
    }

    private function createSendRecord(array $data): int
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $rs  = $this->db->Execute(
            'INSERT INTO email_sends (tenant_id, company_code, template_id, contact_id, to_email, to_name, from_name, from_email, reply_to, subject, preheader, html_body, merge_context, status, queued_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id',
            [
                $data['tenant_id'], $data['company_code'], $data['template_id'], $data['contact_id'],
                $data['to_email'], $data['to_name'], $data['from_name'], $data['from_email'],
                $data['reply_to'], $data['subject'], $data['preheader'], $data['html_body'],
                $data['merge_context'], $data['status'], $now, $now
            ]
        );

        return (!$rs->EOF) ? (int)$rs->fields['id'] : (int)$this->db->Insert_ID();
    }
}
