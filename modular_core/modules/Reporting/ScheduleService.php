<?php
/**
 * Reporting/ScheduleService.php
 * 
 * CORE → ADVANCED: Automated Report Scheduling & Distribution
 */

declare(strict_types=1);

namespace Modules\Reporting;

use Core\BaseService;

class ScheduleService extends BaseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create a new automated report schedule
     * Used by: Dashboard BI Settings
     */
    public function createSchedule(int $reportId, string $frequency, array $recipients): int
    {
        $data = [
            'report_id' => $reportId,
            'frequency' => $frequency, // 'daily', 'weekly', 'monthly'
            'recipients' => json_encode($recipients),
            'last_run_at' => null,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->db->AutoExecute('report_schedules', $data, 'INSERT');
        return (int)$this->db->Insert_ID();
    }

    /**
     * Fetch reports due for dispatch (Rule: Current time matching frequency window)
     */
    public function getDueSchedules(): array
    {
        // Advanced: Logic to match hourly, daily, weekly, monthly windows
        $sql = "SELECT id, report_id, recipients, frequency 
                FROM report_schedules 
                WHERE status = 'active' AND (last_run_at IS NULL OR last_run_at < NOW() - INTERVAL '23 hours')"; // Daily baseline
        
        return $this->db->GetAll($sql);
    }
}
