<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use PDO;
use PDOException;

/**
 * Reads PostgreSQL column and primary-key metadata for schema reflection.
 *
 * @visibility root
 */
final class CatalogQuery
{
    /**
     * Reads columns in their declared ordinal order.
     *
     * @return list<array{column_name: string, data_type: string, character_maximum_length: ?string, numeric_precision: ?string, numeric_scale: ?string, is_nullable: string, column_default: ?string, udt_name: string}>
     */
    public function columns(PDO $pdo, string $schema, string $table): array
    {
        $stmt = $pdo->prepare(
            'SELECT column_name, data_type, character_maximum_length, '
            . 'numeric_precision, numeric_scale, is_nullable, column_default, '
            . 'udt_name '
            . 'FROM information_schema.columns '
            . 'WHERE table_schema = :schema AND table_name = :table '
            . 'ORDER BY ordinal_position'
        );
        $stmt->execute(['schema' => $schema, 'table' => $table]);

        /**
         * @var list<array{column_name: string, data_type: string, character_maximum_length: ?string, numeric_precision: ?string, numeric_scale: ?string, is_nullable: string, column_default: ?string, udt_name: string}> $columns
         */
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $columns;
    }

    /**
     * Reads primary-key names, returning none when the catalog rejects lookup.
     *
     * @return list<string>
     */
    public function primaryKeys(PDO $pdo, string $schema, string $table): array
    {
        $pkStmt = $pdo->prepare(
            'SELECT a.attname '
            . 'FROM pg_index i '
            . 'JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) '
            . 'WHERE i.indrelid = :table_oid::regclass AND i.indisprimary'
        );

        try {
            $qualifiedTable = $schema === 'public' ? "\"{$table}\"" : "\"{$schema}\".\"{$table}\"";
            $pkStmt->execute(['table_oid' => $qualifiedTable]);
            /**
             * @var list<array{attname: string}> $pkRows
             */
            $pkRows = $pkStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            $pkRows = [];
        }

        $primaryKeys = array_map(static fn (array $row): string => $row['attname'], $pkRows);
        return $primaryKeys;
    }
}
