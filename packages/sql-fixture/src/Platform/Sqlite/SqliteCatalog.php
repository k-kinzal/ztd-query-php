<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use PDO;
use RuntimeException;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

/**
 * Reads a table's declaration out of SQLite's own catalog.
 *
 * SQLite keeps the statement each table was created with, which is the best
 * answer there is: it says everything the author wrote, including the parts
 * SQLite does not enforce. Where no such statement is recorded — a table
 * created by `CREATE TABLE AS`, or one the connection built without one —
 * `PRAGMA table_info` still describes the columns, though only as SQLite
 * understands them, with the declared type reduced and defaults reported as
 * text.
 */
final class SqliteCatalog
{
    /**
     * Answers the statement a table was created with, where SQLite recorded one.
     *
     * @param PDO $pdo Connection to read through
     * @param string $tableName Table to describe
     *
     * @return string|null The statement, or null when none was recorded
     */
    public function createTableSqlOf(PDO $pdo, string $tableName): ?string
    {
        $statement = $pdo->prepare('SELECT sql FROM sqlite_schema WHERE type = :type AND name = :name');
        $statement->execute(['type' => 'table', 'name' => $tableName]);

        /** @var array{sql: string}|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false || $row['sql'] === '' ? null : $row['sql'];
    }

    /**
     * Describes a table from what `PRAGMA table_info` reports.
     *
     * The pragma takes no parameter, so the name is reduced to the characters
     * an unquoted identifier may contain before it is written into the
     * statement.
     *
     * @param PDO $pdo Connection to read through
     * @param string $tableName Table to describe
     *
     * @return TableSchema The table as the pragma describes it
     *
     * @throws RuntimeException When the pragma cannot be run, or the connection knows no such table
     */
    public function tableInfo(PDO $pdo, string $tableName): TableSchema
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
        $statement = $pdo->query("PRAGMA table_info({$safeName})");
        if ($statement === false) {
            throw new RuntimeException("Failed to get schema for table: {$tableName}");
        }

        /** @var list<array{cid: int, name: string, type: string, notnull: int, dflt_value: string|null, pk: int}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            throw new RuntimeException("Table not found: {$tableName}");
        }

        $columns = [];
        $primaryKeys = [];
        foreach ($rows as $row) {
            $isPrimaryKey = $row['pk'] > 0;
            if ($isPrimaryKey) {
                $primaryKeys[] = $row['name'];
            }

            $type = strtoupper($row['type'] !== '' ? $row['type'] : 'BLOB');
            $length = null;
            $precision = null;
            $scale = null;
            if (preg_match('/^(\w+)\s*\(\s*(\d+)\s*(?:,\s*(\d+)\s*)?\)/i', $type, $matches) === 1) {
                $type = strtoupper($matches[1]);
                if (isset($matches[3])) {
                    $precision = (int) $matches[2];
                    $scale = (int) $matches[3];
                } else {
                    $length = (int) $matches[2];
                }
            }

            $columns[$row['name']] = new ColumnDefinition(
                name: $row['name'],
                type: $type,
                length: $length,
                precision: $precision,
                scale: $scale,
                nullable: !$isPrimaryKey && $row['notnull'] === 0,
                unsigned: false,
                default: $this->defaultValueOf($row['dflt_value']),
                autoIncrement: false,
                generated: false,
                enumValues: null,
            );
        }

        return new TableSchema($tableName, $columns, $primaryKeys);
    }

    /**
     * Reads a default the pragma reported, as the type it is written as.
     *
     * The pragma reports every default as the text it was written as, quotes
     * included, so a string default arrives quoted and a number arrives as
     * digits.
     *
     * @param string|null $written Default as the pragma reported it
     *
     * @return int|float|string|null The default, or null when none was declared
     */
    public function defaultValueOf(?string $written): int|float|string|null
    {
        if ($written === null || strtoupper($written) === 'NULL') {
            return null;
        }
        if (preg_match("/^['\"](.*)['\"]\s*$/s", $written, $matches) === 1) {
            return $matches[1];
        }
        if (is_numeric($written)) {
            return str_contains($written, '.') ? (float) $written : (int) $written;
        }

        return $written;
    }
}
