<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use PDO;
use RuntimeException;

/**
 * Reads the database CREATE TABLE declaration.
 *
 * @visibility root
 */
final class CreateTableQuery
{
    /**
     * Fetch the CREATE TABLE SQL from the database.
     * @throws RuntimeException
     */
    public function fetchCreateTableSql(PDO $pdo, string $tableName): string
    {
        $quotedName = (new IdentifierQuoter())->quoteTableName($tableName);

        $stmt = $pdo->query("SHOW CREATE TABLE {$quotedName}");
        if ($stmt === false) {
            throw new RuntimeException("Failed to get CREATE TABLE for: {$tableName}");
        }

        /**
         * @var array{0: string, 1: string}|false $row
         */
        $row = $stmt->fetch(PDO::FETCH_NUM);
        if ($row === false) {
            throw new RuntimeException("Table not found: {$tableName}");
        }

        return $row[1];
    }
}
