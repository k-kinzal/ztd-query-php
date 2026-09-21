<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Schema\ColumnDefinition;

/**
 * Converts an information_schema.columns row into a column definition.
 *
 * @visibility root
 */
final class CatalogColumn
{
    /**
     * Maps the catalog data type to the type name the value generators expect.
     *
     * @param array{data_type: string, udt_name: string} $row
     */
    public function resolveType(array $row): string
    {
        $type = strtoupper($row['data_type']);

        if ($type === 'ARRAY') {
            return strtoupper(ltrim($row['udt_name'], '_')) . '_ARRAY';
        }

        if ($type === 'USER-DEFINED') {
            return strtoupper($row['udt_name']);
        }

        return $type;
    }

    /**
     * Builds the column, reading its default through the grammar and marking sequence and identity columns.
     *
     * @param array{column_name: string, data_type: string, character_maximum_length: ?string, numeric_precision: ?string, numeric_scale: ?string, is_nullable: string, column_default: ?string, udt_name: string, is_identity: string, is_generated: string} $row
     */
    public function parse(array $row, CatalogExpression $expressions, bool $primaryKey): ColumnDefinition
    {
        $default = $row['column_default'];
        $sequence = $default !== null && $expressions->isSequence($default);
        $autoIncrement = $sequence || $row['is_identity'] === 'YES';

        return new ColumnDefinition(
            name: $row['column_name'],
            type: $this->resolveType($row),
            length: $row['character_maximum_length'] !== null ? (int) $row['character_maximum_length'] : null,
            precision: $row['numeric_precision'] !== null ? (int) $row['numeric_precision'] : null,
            scale: $row['numeric_scale'] !== null ? (int) $row['numeric_scale'] : null,
            nullable: $row['is_nullable'] === 'YES' && !$primaryKey,
            unsigned: false,
            default: $default === null || $sequence ? null : $expressions->evaluate($default),
            autoIncrement: $autoIncrement,
            generated: $row['is_generated'] === 'ALWAYS',
            enumValues: null,
        );
    }
}
