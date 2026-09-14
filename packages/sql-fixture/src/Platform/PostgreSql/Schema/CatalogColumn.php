<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

/**
 * Interprets PostgreSQL catalog type and default metadata.
 *
 * @visibility root
 */
final class CatalogColumn
{
    /**
     * @param array{data_type: string, character_maximum_length: ?string, numeric_precision: ?string, numeric_scale: ?string, udt_name: string} $col
     */
    public function mapDataType(array $col): string
    {
        $type = strtoupper($col['data_type']);

        if ($type === 'CHARACTER VARYING' && $col['character_maximum_length'] !== null) {
            return 'VARCHAR(' . $col['character_maximum_length'] . ')';
        }

        if ($type === 'CHARACTER' && $col['character_maximum_length'] !== null) {
            return 'CHAR(' . $col['character_maximum_length'] . ')';
        }

        if ($type === 'NUMERIC' && $col['numeric_precision'] !== null) {
            $precision = $col['numeric_precision'];
            if ($col['numeric_scale'] !== null && $col['numeric_scale'] !== '0') {
                return "NUMERIC({$precision}, {$col['numeric_scale']})";
            }
            return "NUMERIC({$precision})";
        }

        if ($type === 'ARRAY') {
            return strtoupper($col['udt_name']);
        }

        if ($type === 'USER-DEFINED') {
            return strtoupper($col['udt_name']);
        }

        return $type;
    }

    /**
     * @param array{data_type: string, udt_name: string, character_maximum_length: ?string, numeric_precision: ?string, numeric_scale: ?string} $row
     */
    public function resolveType(array $row): string
    {
        $type = strtoupper($row['data_type']);

        if ($type === 'ARRAY') {
            $elementType = strtoupper(ltrim($row['udt_name'], '_'));
            return $elementType . '_ARRAY';
        }

        if ($type === 'USER-DEFINED') {
            return strtoupper($row['udt_name']);
        }

        return $type;
    }

    /**
     * Parses default.
     */
    public function parseDefault(?string $value): int|float|bool|string|null
    {
        if ($value === null) {
            return null;
        }

        if (strtoupper($value) === 'NULL' || strtoupper($value) === 'NULL::') {
            return null;
        }

        if (str_contains($value, 'nextval(')) {
            return null;
        }

        if (preg_match("/^'(.*)'::.*$/s", $value, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match("/^'(.*)'$/s", $value, $matches) === 1) {
            return $matches[1];
        }

        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }

        if (is_numeric($value)) {
            if (str_contains($value, '.')) {
                return (float) $value;
            }
            return (int) $value;
        }

        return $value;
    }
}
