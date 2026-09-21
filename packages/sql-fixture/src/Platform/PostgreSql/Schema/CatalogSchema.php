<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use PDO;
use RuntimeException;
use SqlFixture\Schema\TableSchema;

/**
 * Builds a schema from information_schema columns and the catalog primary key.
 *
 * @visibility root
 */
final class CatalogSchema
{
    /**
     * Keeps the grammar-backed reader for catalog default expressions.
     */
    public function __construct(private readonly CatalogExpression $expressions)
    {
    }

    /**
     * Reads the named table, which may carry a schema qualifier, from the catalog.
     * @throws RuntimeException
     */
    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        $schema = 'public';
        $table = $tableName;
        if (str_contains($tableName, '.')) {
            $parts = explode('.', $tableName, 2);
            $schema = $parts[0];
            $table = $parts[1];
        }

        $rows = (new CatalogQuery())->columns($pdo, $schema, $table);
        if ($rows === []) {
            throw new RuntimeException("Table not found: {$tableName}");
        }
        $primaryKeys = (new CatalogQuery())->primaryKeys($pdo, $schema, $table);

        $columns = [];
        foreach ($rows as $row) {
            $column = (new CatalogColumn())->parse($row, $this->expressions, in_array($row['column_name'], $primaryKeys, true));
            $columns[$column->name] = $column;
        }

        return new TableSchema($table, $columns, $primaryKeys);
    }
}
