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
            return $this->elementType($row['udt_name']) . '_ARRAY';
        }

        if ($type === 'USER-DEFINED') {
            return strtoupper($row['udt_name']);
        }

        return $type;
    }

    /**
     * Names the declared type of an array element from the catalog type of the array, such as `_int4`.
     *
     * @param string $udtName Catalog type name of the array, with its leading underscore
     *
     * @return string The type name a CREATE TABLE declares, such as `INTEGER`
     */
    public function elementType(string $udtName): string
    {
        return (new TypeDeclaration())->catalogType(strtolower(ltrim($udtName, '_')));
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
