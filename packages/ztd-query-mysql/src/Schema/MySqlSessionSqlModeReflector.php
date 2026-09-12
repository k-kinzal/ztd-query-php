<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Connection\ConnectionInterface;

/**
 * Implements the My Sql Session Sql Mode Reflector contract for MySQL.
 */
final class MySqlSessionSqlModeReflector
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    /**
     * Reflect for the supplied MySQL input.
     */
    public function reflect(): string
    {
        $statement = $this->connection->query('SELECT @@SESSION.sql_mode AS ztd_sql_mode');
        if ($statement === false) {
            return '';
        }
        $row = $statement->fetchAll()[0] ?? [];
        $sqlMode = $row['ztd_sql_mode'] ?? '';

        return is_string($sqlMode) ? $sqlMode : '';
    }
}
