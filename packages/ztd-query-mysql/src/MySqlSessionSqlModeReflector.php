<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Connection\ConnectionInterface;

final class MySqlSessionSqlModeReflector
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

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
