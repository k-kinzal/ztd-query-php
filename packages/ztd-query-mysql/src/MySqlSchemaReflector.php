<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\SchemaReflector;

/**
 * Fetches MySQL schema information via SQL queries.
 */
final class MySqlSchemaReflector implements SchemaReflector
{
    /**
     * Connection instance used to issue schema queries.
     */
    private ConnectionInterface $connection;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateStatement(string $tableName): ?string
    {
        $stmt = $this->connection->query("SHOW CREATE TABLE `$tableName`");
        if ($stmt === false) {
            return null;
        }

        $rows = $stmt->fetchAll();
        if ($rows === [] || !isset($rows[0]['Create Table']) || !is_string($rows[0]['Create Table'])) {
            return null;
        }

        return $rows[0]['Create Table'];
    }

    /**
     * {@inheritDoc}
     */
    public function reflectAll(): array
    {
        $stmt = $this->connection->query('SHOW TABLES');
        if ($stmt === false) {
            return [];
        }

        $tables = $stmt->fetchAll();
        $result = [];

        foreach ($tables as $row) {
            $values = array_values($row);
            $tableName = $values[0] ?? null;
            if (!is_string($tableName) || $tableName === '') {
                continue;
            }

            $createSql = $this->getCreateStatement($tableName);
            if ($createSql !== null) {
                $result[$tableName] = $createSql;
            }
        }

        return $result;
    }
}
