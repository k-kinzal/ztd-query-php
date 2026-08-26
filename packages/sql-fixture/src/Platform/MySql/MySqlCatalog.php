<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use PDO;
use RuntimeException;

/**
 * Reads a table's declaration out of MySQL's own catalog.
 *
 * MySQL will hand back the statement a table was created with, which is the
 * whole answer and needs no reassembling. It will not take that table's name
 * as a parameter, though, so the name has to be written into the statement,
 * and quoting it is what keeps a name that needs quoting from changing what
 * is asked.
 */
final class MySqlCatalog
{
    /**
     * Answers the statement a table was created with.
     *
     * @param PDO $pdo Connection to read through
     * @param string $tableName Table to describe, optionally database-qualified
     *
     * @return string The CREATE TABLE statement
     *
     * @throws RuntimeException When the statement cannot be run, or the connection knows no such table
     */
    public function createTableSqlOf(PDO $pdo, string $tableName): string
    {
        $statement = $pdo->query('SHOW CREATE TABLE ' . $this->quoted($tableName));
        if ($statement === false) {
            throw new RuntimeException("Failed to get CREATE TABLE for: {$tableName}");
        }

        /** @var array{0: string, 1: string}|false $row */
        $row = $statement->fetch(PDO::FETCH_NUM);
        if ($row === false) {
            throw new RuntimeException("Table not found: {$tableName}");
        }

        return $row[1];
    }

    /**
     * Quotes a table name so it can be written into a statement.
     *
     * A qualified name is two identifiers with a dot between them, and each is
     * quoted on its own — quoting the whole thing would name one table whose
     * name contains a dot.
     *
     * @param string $tableName Name as the caller wrote it
     *
     * @return string The name in backticks
     */
    public function quoted(string $tableName): string
    {
        if (!str_contains($tableName, '.')) {
            return '`' . $tableName . '`';
        }

        [$database, $table] = explode('.', $tableName, 2);

        return '`' . $database . '`.`' . $table . '`';
    }
}
