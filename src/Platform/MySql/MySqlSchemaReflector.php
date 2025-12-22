<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Schema\SchemaReflector;
use PDO;

/**
 * Fetches MySQL schema information via SQL queries.
 */
final class MySqlSchemaReflector implements SchemaReflector
{
    /**
     * PDO instance used to issue schema queries.
     */
    private PDO $pdo;

    /**
     * Cached primary keys per table.
     *
     * @var array<string, array<int, string>>
     */
    private array $primaryKeysCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateStatement(string $tableName): ?string
    {
        $stmt = $this->pdo->query("SHOW CREATE TABLE `$tableName`");
        if ($stmt === false) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !isset($row['Create Table']) || !is_string($row['Create Table'])) {
            return null;
        }

        return $row['Create Table'];
    }

    /**
     * {@inheritDoc}
     */
    public function getPrimaryKeys(string $tableName): array
    {
        if (isset($this->primaryKeysCache[$tableName])) {
            return $this->primaryKeysCache[$tableName];
        }

        $stmt = $this->pdo->query("SHOW KEYS FROM `$tableName` WHERE Key_name = 'PRIMARY'");
        if ($stmt === false) {
            return [];
        }

        $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
        /** @var array<int, array<string, mixed>> $keys */
        $primaryKeys = [];
        foreach ($keys as $key) {
            if (isset($key['Column_name']) && is_string($key['Column_name'])) {
                $primaryKeys[] = $key['Column_name'];
            }
        }

        $this->primaryKeysCache[$tableName] = $primaryKeys;

        return $primaryKeys;
    }
}
