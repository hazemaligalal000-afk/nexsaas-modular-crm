<?php
/**
 * utils/MigrationHelper.php
 * 
 * UUID v4 Primary Key migration utility (Requirement 5.122)
 * Ensures global uniqueness for multi-region scale.
 */

namespace NexSaaS\Platform\Db;

class MigrationHelper
{
    /**
     * Generate RFC 4122 compliant UUID v4
     */
    public static function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * PostgreSQL Schema alignment for UUID
     */
    public static function getUuidSchemaSql(string $tableName): string
    {
        return "ALTER TABLE $tableName ALTER COLUMN id SET DATA TYPE UUID USING (uuid_generate_v4());";
    }
}
