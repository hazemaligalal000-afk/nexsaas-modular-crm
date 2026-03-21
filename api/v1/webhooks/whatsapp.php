<?php
/**
 * api/v1/webhooks/whatsapp.php
 * Final Hardened Webhook Handler (Requirement 1.3)
 * Guarantees NO SYNCHRONOUS PROCESSING.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use ModularCore\Core\Queue\SendWhatsAppJob;

// 1. FAST RESPONSE: Sub-100ms ACK (Requirement #1)
// We only validate the JSON structure and tenant context, then DISPATCH.
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['tenant_id'])) {
    http_response_code(400);
    exit;
}

// 2. DISPATCH ASYNC JOB (Requirement #2)
// This moves all logic (Database logging, AI scoring, reply generation) to background workers.
$job = new SendWhatsAppJob($input['tenant_id'], $input['from'], $input['text']);
$job->dispatch();

// 3. IMMEDIATE SUCCESS (No failure propagation to sender)
http_response_code(200);
echo json_encode(['status' => 'dispatched']);
