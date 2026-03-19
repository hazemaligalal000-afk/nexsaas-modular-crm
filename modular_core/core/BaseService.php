<?php
/**
 * Core/BaseService.php
 *
 * Abstract base service class for business logic layer
 */

declare(strict_types=1);

namespace Core;

abstract class BaseService
{
    /** @var \ADOConnection */
    protected $db;

    /**
     * @param \ADOConnection $db ADOdb connection
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Execute a database transaction
     * 
     * @param callable $callback
     * @return mixed
     * @throws \Exception
     */
    protected function transaction(callable $callback)
    {
        $this->db->StartTrans();
        
        try {
            $result = $callback();
            $this->db->CompleteTrans();
            return $result;
        } catch (\Exception $e) {
            $this->db->FailTrans();
            throw $e;
        }
    }

    /**
     * Get database connection
     * 
     * @return \ADOConnection
     */
    public function getDb()
    {
        return $this->db;
    }
}
