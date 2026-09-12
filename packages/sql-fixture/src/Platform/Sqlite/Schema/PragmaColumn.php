<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use SqlFixture\Schema\ColumnDefinition;

/**
 * Converts a SQLite table_info row to a column definition.
 *
 * @visibility root
 */
final class PragmaColumn
{
    /**
     * Applies declared type parameters, nullability and the primary-key constraint.
     *
     * @param array{cid: int, name: string, type: string, notnull: int, dflt_value: string|null, pk: int} $row
     */
    public function parse(array $row): ColumnDefinition
    {
        $columnName = $row['name'];
        $type = strtoupper($row['type'] !== '' ? $row['type'] : 'BLOB');
        $nullable = $row['notnull'] === 0;
        $default = (new PragmaSchema())->parseDefaultValue($row['dflt_value']);
        $isPrimaryKey = $row['pk'] > 0;

        if ($isPrimaryKey) {
            $nullable = false;
        }

        $length = null;
        $precision = null;
        $scale = null;

        if (preg_match('/^(\w+)\s*\(\s*(\d+)\s*(?:,\s*(\d+)\s*)?\)/i', $type, $typeMatches) === 1) {
            $type = strtoupper($typeMatches[1]);
            if (isset($typeMatches[3])) {
                $precision = (int) $typeMatches[2];
                $scale = (int) $typeMatches[3];
            } else {
                $length = (int) $typeMatches[2];
            }
        }

        $autoIncrement = false;

        return new ColumnDefinition(
            name: $columnName,
            type: $type,
            length: $length,
            precision: $precision,
            scale: $scale,
            nullable: $nullable,
            unsigned: false,
            default: $default,
            autoIncrement: $autoIncrement,
            generated: false,
            enumValues: null,
        );
    }
}
