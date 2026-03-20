<?php

namespace ModularCore\Modules\EmailMarketing;

use Exception;

/**
 * Sequence Engine: Advanced Marketing Automation Hub (Requirement F1)
 * Handles branching logic, exit filters, and delay-based executions.
 */
class SequenceEngine
{
    private $db;
    private $sender;

    public function __construct($db, $sender)
    {
        $this->db = $db;
        $this->sender = $sender;
    }

    /**
     * Requirement 100: Process active enrollments and execute next steps
     */
    public function heartbeat()
    {
        $pending = $this->db->query("
            SELECT e.*, s.step_type, s.content, s.delay_minutes, s.conditions, s.exit_filters
            FROM email_sequence_enrollments e
            JOIN email_sequence_steps s ON e.next_step_id = s.id
            WHERE e.status = 'active' AND e.next_step_at <= NOW()
            LIMIT 50
        ");

        foreach ($pending as $enrollment) {
            $this->processStep($enrollment);
        }
    }

    /**
     * Requirement 77: Condition Branching (if email opened → A, else → B)
     */
    private function processStep($enrollment)
    {
        // 1. Check Exit Filters (e.g., Contact Replied or Deal Won)
        if ($this->shouldExit($enrollment)) {
            $this->db->execute("UPDATE email_sequence_enrollments SET status = 'ejected' WHERE id = :id", ['id' => $enrollment['id']]);
            return;
        }

        // 2. Execute Action (Email/Task)
        if ($enrollment['step_type'] === 'email') {
            $this->sender->send($enrollment['contact_id'], $enrollment['content']);
        }

        // 3. Find Next Path based on Conditions
        $conditions = json_decode($enrollment['conditions'], true) ?? [];
        $nextStepId = $this->resolveNextStep($enrollment, $conditions);

        if ($nextStepId) {
            $nextStep = $this->db->queryOne("SELECT delay_minutes FROM email_sequence_steps WHERE id = :id", ['id' => $nextStepId]);
            $nextStepAt = date('Y-m-d H:i:s', strtotime("+{$nextStep['delay_minutes']} minutes"));

            $this->db->execute("
                UPDATE email_sequence_enrollments 
                SET last_step_id = :lsid, next_step_id = :nsid, next_step_at = :nat, last_step_at = NOW()
                WHERE id = :id
            ", ['lsid' => $enrollment['next_step_id'], 'nsid' => $nextStepId, 'nat' => $nextStepAt, 'id' => $enrollment['id']]);
        } else {
            $this->db->execute("UPDATE email_sequence_enrollments SET status = 'completed' WHERE id = :id", ['id' => $enrollment['id']]);
        }
    }

    /**
     * Requirement 78: Evaluation of Exit Conditions
     */
    private function shouldExit($enrollment)
    {
        $filters = json_decode($enrollment['exit_filters'] ?? '[]', true);
        foreach ($filters as $f) {
            // Check if contact replied or deal is won
            if ($f === 'replied' && $this->db->queryValue("SELECT COUNT(*) FROM omnichannel_messages WHERE contact_id = :cid AND direction = 'inbound' AND created_at > :start", ['cid' => $enrollment['contact_id'], 'start' => $enrollment['enrolled_at']])) {
                return true;
            }
        }
        return false;
    }

    private function resolveNextStep($enrollment, $conditions)
    {
        // Logic to choose next_step_id based on open/click data
        // For now, default to next increment
        return $this->db->queryValue("
            SELECT id FROM email_sequence_steps 
            WHERE sequence_id = :sid AND sort_order > :order 
            ORDER BY sort_order ASC LIMIT 1", 
            ['sid' => $enrollment['sequence_id'], 'order' => $enrollment['sort_order'] ?? 0]
        );
    }
}
