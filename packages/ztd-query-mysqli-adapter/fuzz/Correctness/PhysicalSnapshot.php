<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use Error;
use mysqli;
use mysqli_result;

/**
 * Checks every physical table rather than comparing the oracle database to itself.
 */
final class PhysicalSnapshot
{
    /**
     * @throws Error When catalog inspection does not return a result.
     */
    public static function capture(mysqli $connection): string
    {
        $tables = $connection->query('SHOW TABLES');
        if (!$tables instanceof mysqli_result) {
            throw new Error('Could not inspect physical catalog.');
        }
        $snapshot = [];
        foreach ($tables->fetch_all(MYSQLI_NUM) as $row) {
            $table = $row[0];
            if (!is_string($table)) {
                throw new Error('Invalid physical table name.');
            }
            $quoted = '`' . str_replace('`', '``', $table) . '`';
            $data = $connection->query('SELECT * FROM ' . $quoted);
            $schema = $connection->query('SHOW CREATE TABLE ' . $quoted);
            if (!$data instanceof mysqli_result || !$schema instanceof mysqli_result) {
                throw new Error('Could not inspect physical table.');
            }
            $rows = array_map('serialize', $data->fetch_all(MYSQLI_ASSOC));
            sort($rows, SORT_STRING);
            $snapshot[$table] = [$schema->fetch_all(MYSQLI_ASSOC), $rows];
            $data->free();
            $schema->free();
        }
        $tables->free();
        ksort($snapshot);

        return serialize($snapshot);
    }
}
