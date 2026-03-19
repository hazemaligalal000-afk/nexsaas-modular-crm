<?php
/**
 * CRM/Workflows/WorkflowTriggerMatcher.php
 *
 * Checks whether a workflow's trigger configuration matches the incoming
 * event context. Used by WorkflowEngine::evaluate() to filter candidates.
 *
 * Requirements: 14.1, 14.2
 */

declare(strict_types=1);

namespace CRM\Workflows;

class WorkflowTriggerMatcher
{
    /**
     * Determine whether the given workflow row matches the event + context.
     *
     * @param  array  $workflow  Row from the `workflows` table (includes trigger_type, trigger_config, module)
     * @param  string $event     Normalised event name, e.g. 'record_created', 'field_value_changed'
     * @param  array  $context   Event context: tenant_id, company_code, record_type, record_id, event,
     *                           changed_fields (array), field_name, old_value, new_value, scheduled_at, etc.
     * @return bool
     */
    public function matches(array $workflow, string $event, array $context): bool
    {
        // Trigger type must match the event
        if ($workflow['trigger_type'] !== $event) {
            return false;
        }

        $config = is_string($workflow['trigger_config'])
            ? (json_decode($workflow['trigger_config'], true) ?? [])
            : ($workflow['trigger_config'] ?? []);

        // Module filter: workflow must apply to the same module
        if (!empty($workflow['module']) && !empty($context['module'])) {
            if ($workflow['module'] !== $context['module']) {
                return false;
            }
        }

        return match ($event) {
            'record_created'            => $this->matchRecordCreated($config, $context),
            'record_updated'            => $this->matchRecordUpdated($config, $context),
            'field_value_changed'       => $this->matchFieldValueChanged($config, $context),
            'date_time_reached'         => $this->matchDateTimeReached($config, $context),
            'inbound_message_received'  => $this->matchInboundMessage($config, $context),
            'manual'                    => true,  // manual triggers always match when explicitly fired
            default                     => false,
        };
    }

    // -------------------------------------------------------------------------
    // Per-trigger-type matchers
    // -------------------------------------------------------------------------

    /**
     * record_created: optionally filter by source or owner_id in trigger_config.
     */
    private function matchRecordCreated(array $config, array $context): bool
    {
        if (!empty($config['source']) && ($context['source'] ?? null) !== $config['source']) {
            return false;
        }

        if (!empty($config['owner_id']) && ($context['owner_id'] ?? null) != $config['owner_id']) {
            return false;
        }

        return true;
    }

    /**
     * record_updated: optionally restrict to specific fields being changed.
     */
    private function matchRecordUpdated(array $config, array $context): bool
    {
        // If trigger_config specifies watched_fields, at least one must be in changed_fields
        if (!empty($config['watched_fields'])) {
            $changedFields = $context['changed_fields'] ?? [];
            $intersection  = array_intersect((array) $config['watched_fields'], (array) $changedFields);
            if (empty($intersection)) {
                return false;
            }
        }

        return true;
    }

    /**
     * field_value_changed: must match field_name; optionally match old/new values.
     */
    private function matchFieldValueChanged(array $config, array $context): bool
    {
        // If no field_name constraint in config, match any field change
        if (empty($config['field_name'])) {
            return true;
        }

        if (($context['field_name'] ?? null) !== $config['field_name']) {
            return false;
        }

        // Optional: match specific old_value
        if (array_key_exists('old_value', $config) && $config['old_value'] !== null) {
            if (($context['old_value'] ?? null) != $config['old_value']) {
                return false;
            }
        }

        // Optional: match specific new_value
        if (array_key_exists('new_value', $config) && $config['new_value'] !== null) {
            if (($context['new_value'] ?? null) != $config['new_value']) {
                return false;
            }
        }

        return true;
    }

    /**
     * date_time_reached: context must carry a scheduled_at that is <= now.
     * If no scheduled_at in context, match if trigger_config has no constraint.
     */
    private function matchDateTimeReached(array $config, array $context): bool
    {
        $scheduledAt = $context['scheduled_at'] ?? null;
        if ($scheduledAt === null) {
            // No scheduled_at in context — match if config has no time constraint
            return empty($config['scheduled_at']);
        }

        try {
            $scheduled = new \DateTimeImmutable($scheduledAt, new \DateTimeZone('UTC'));
            $now       = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            return $scheduled <= $now;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * inbound_message_received: optionally filter by channel.
     */
    private function matchInboundMessage(array $config, array $context): bool
    {
        if (!empty($config['channel']) && ($context['channel'] ?? null) !== $config['channel']) {
            return false;
        }

        return true;
    }
}
