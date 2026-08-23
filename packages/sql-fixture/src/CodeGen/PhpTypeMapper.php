<?php

declare(strict_types=1);

namespace SqlFixture\CodeGen;

use SqlFixture\Schema\ColumnDefinition;

/**
 * Works out how a column is spelt in PHP.
 *
 * Two answers are needed and they differ. The native type goes in a parameter
 * declaration, where PHP has to be able to enforce it. The documented type
 * goes in phpdoc, where a static analyser can be told rather more: that an
 * ENUM column only ever holds one of its declared values, for instance, which
 * turns a wrong literal into an error at the call site.
 */
final class PhpTypeMapper
{
    private const INTEGER_TYPES = [
        'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT',
        'INT2', 'INT8', 'YEAR', 'BIT', 'SERIAL', 'BIGSERIAL', 'SMALLSERIAL',
    ];

    private const FLOAT_TYPES = [
        'FLOAT', 'DOUBLE', 'REAL', 'DOUBLE PRECISION',
    ];

    private const BOOLEAN_TYPES = [
        'BOOL', 'BOOLEAN',
    ];

    private const STRING_TYPES = [
        'CHAR', 'VARCHAR', 'CHARACTER', 'CHARACTER VARYING', 'TINYTEXT', 'TEXT',
        'MEDIUMTEXT', 'LONGTEXT', 'CLOB', 'BINARY', 'VARBINARY', 'TINYBLOB',
        'BLOB', 'MEDIUMBLOB', 'LONGBLOB', 'BYTEA', 'SET', 'JSON', 'JSONB',
        'DATE', 'TIME', 'DATETIME', 'TIMESTAMP', 'UUID', 'INET', 'CIDR',
        'DECIMAL', 'NUMERIC', 'DEC', 'FIXED', 'MONEY',
        'POINT', 'LINESTRING', 'POLYGON', 'MULTIPOINT', 'MULTILINESTRING',
        'MULTIPOLYGON', 'GEOMETRY', 'GEOMETRYCOLLECTION',
    ];

    /**
     * The type PHP itself will check on a parameter.
     */
    public function nativeType(ColumnDefinition $column): string
    {
        $type = strtoupper($column->type);

        if (in_array($type, self::INTEGER_TYPES, true)) {
            return 'int';
        }
        if (in_array($type, self::FLOAT_TYPES, true)) {
            return 'float';
        }
        if (in_array($type, self::BOOLEAN_TYPES, true)) {
            return 'bool';
        }
        if ($type === 'ENUM' || in_array($type, self::STRING_TYPES, true)) {
            return 'string';
        }

        return 'mixed';
    }

    /**
     * The type an analyser is told, which may be narrower than PHP can check.
     */
    public function documentedType(ColumnDefinition $column): string
    {
        $type = $this->enumLiterals($column) ?? $this->nativeType($column);

        if ($type === 'mixed') {
            return 'mixed';
        }

        return $column->nullable ? $type . '|null' : $type;
    }

    /**
     * The documented type of an override, which is always optional.
     */
    public function overrideType(ColumnDefinition $column): string
    {
        $type = $this->enumLiterals($column) ?? $this->nativeType($column);

        return $type === 'mixed' ? 'mixed' : $type . '|null';
    }

    /**
     * An ENUM holds one of a known set, so say which.
     */
    private function enumLiterals(ColumnDefinition $column): ?string
    {
        if (strtoupper($column->type) !== 'ENUM' || $column->enumValues === null || $column->enumValues === []) {
            return null;
        }

        return implode('|', array_map(
            static fn (string $value): string => "'" . str_replace("'", "\\'", $value) . "'",
            $column->enumValues
        ));
    }
}
