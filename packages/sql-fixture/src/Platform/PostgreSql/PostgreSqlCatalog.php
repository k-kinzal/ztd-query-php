<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

use PDO;
use PDOException;

/**
 * Reads a table's declaration out of PostgreSQL's own catalog.
 *
 * PostgreSQL has no `SHOW CREATE TABLE`, so a live table can only be described
 * by asking `information_schema` what columns it has and `pg_index` which of
 * them the primary key is made of. Neither answer is a declaration; turning
 * them back into one is somebody else's job.
 */
final class PostgreSqlCatalog
{
    /**
     * Separates a name into the schema it lives in and the table itself.
     *
     * A name written without a schema is in `public`, which is where
     * PostgreSQL puts a table nobody said otherwise about.
     *
     * @param string $tableName Name as the caller wrote it
     *
     * @return array{string, string} The schema and the table
     */
    public function split(string $tableName): array
    {
        if (!str_contains($tableName, '.')) {
            return ['public', $tableName];
        }

        [$schema, $table] = explode('.', $tableName, 2);

        return [$schema, $table];
    }

    /**
     * Answers every column of a table, in the order it was declared.
     *
     * @param PDO $pdo Connection to read through
     * @param string $schema Schema the table lives in
     * @param string $table Table to describe
     *
     * @return list<array{column_name: string, data_type: string, character_maximum_length: ?string, numeric_precision: ?string, numeric_scale: ?string, is_nullable: string, column_default: ?string, udt_name: string}> One row per column
     */
    public function columnsOf(PDO $pdo, string $schema, string $table): array
    {
        $statement = $pdo->prepare(
            'SELECT column_name, data_type, character_maximum_length, '
            . 'numeric_precision, numeric_scale, is_nullable, column_default, '
            . 'udt_name '
            . 'FROM information_schema.columns '
            . 'WHERE table_schema = :schema AND table_name = :table '
            . 'ORDER BY ordinal_position',
        );
        $statement->execute(['schema' => $schema, 'table' => $table]);

        /** @var list<array{column_name: string, data_type: string, character_maximum_length: ?string, numeric_precision: ?string, numeric_scale: ?string, is_nullable: string, column_default: ?string, udt_name: string}> $columns */
        $columns = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $columns;
    }

    /**
     * Answers the columns the primary key is made of.
     *
     * The key is looked up by casting the table's name to a `regclass`, which
     * is the only way to reach `pg_index` from a name. A name that resolves to
     * nothing raises rather than returning empty, a connection that cannot run
     * the statement at all refuses it, and a table with no key is not an error.
     * All three are answered as no key at all.
     *
     * @param PDO $pdo Connection to read through
     * @param string $schema Schema the table lives in
     * @param string $table Table to describe
     *
     * @return list<string> Column names the key is made of
     */
    public function primaryKeysOf(PDO $pdo, string $schema, string $table): array
    {
        try {
            $statement = $pdo->prepare(
                'SELECT a.attname '
                . 'FROM pg_index i '
                . 'JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) '
                . 'WHERE i.indrelid = :table_oid::regclass AND i.indisprimary',
            );
            if ($statement === false) {
                return [];
            }
            $qualified = $schema === 'public' ? "\"{$table}\"" : "\"{$schema}\".\"{$table}\"";
            $statement->execute(['table_oid' => $qualified]);
            /** @var list<array{attname: string}> $rows */
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return [];
        }

        return array_map(static fn (array $row): string => $row['attname'], $rows);
    }
}
