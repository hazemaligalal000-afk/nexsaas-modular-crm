<?php
/**
 * Core/EventBus.php
 * 
 * ADVANCED: Global Event-Driven Architecture (EDA)
 * Allows modules to decouple and scale independently.
 */

declare(strict_types=1);

namespace Core;

class EventBus
{
    private static array $listeners = [];
    private \ADOConnection $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Subscribe a handler to an event
     */
    public static function listen(string $event, callable $handler): void
    {
        self::$listeners[$event][] = $handler;
    }

    /**
     * Fire an event across all modules
     */
    public function fire(string $event, array $payload): void
    {
        // 1. Synchronous execution
        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $handler) {
                call_user_func($handler, $payload);
            }
        }

        // 2. Persistent log for auditing/async (Batch E)
        $this->db->Execute(
            "INSERT INTO system_events (event_name, payload, fired_at) VALUES (?, ?, NOW())",
            [$event, json_encode($payload)]
        );

        // 3. Integration with Workflows (Advanced)
        // If Workflows module exists, trigger its orchestrator
        if (class_exists('Modules\Workflows\WorkflowOrchestrator')) {
            // This would call the Workflow engine
        }
    }
}
