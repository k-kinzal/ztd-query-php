<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use PDO;

/**
 * Reads the database CREATE TABLE declaration.
 *
 * @visibility root
 */
final class CreateTableQuery
{
    /**
     * Fetch the CREATE TABLE SQL from sqlite_schema.
     */
    public function fetchCreateTableSql(PDO $pdo, string $tableName): ?string
    {
        $stmt = $pdo->prepare(
            'SELECT sql FROM sqlite_schema WHERE type = :type AND name = :name'
        );
        $stmt->execute(['type' => 'table', 'name' => $tableName]);

        /**
         * @var array{sql: string}|false $row
         */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false || $row['sql'] === '') {
            return null;
        }

        return $row['sql'];
    }
}
