<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * Structured column type representation.
 *
 * Combines a platform-independent type family with the platform-specific
 * native type string. Immutable value object.
 */
final class ColumnType
{
    /**
     * @param ColumnTypeFamily $family The abstract type family.
     * @param string $nativeType Platform-specific type string (e.g. "SIGNED", "INTEGER", "int4").
     */
    public function __construct(
        public readonly ColumnTypeFamily $family,
        public readonly string $nativeType,
    ) {
    }

    public static function fromNativeType(string $nativeType): self
    {
        $upper = strtoupper($nativeType);
        $withoutParameters = preg_replace('/\(.*\)/', '', $upper);
        $baseType = trim(is_string($withoutParameters) ? $withoutParameters : $upper);
        $withoutArray = preg_replace('/\[\s*\]$/', '', $baseType);
        $baseType = is_string($withoutArray) ? $withoutArray : $baseType;

        $family = match ($baseType) {
            'INT', 'INT2', 'INT4', 'INT8', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT',
            'SERIAL', 'SMALLSERIAL', 'BIGSERIAL', 'LONG', 'LONGLONG', 'SHORT', 'INT24', 'YEAR', 'BIT'
                => ColumnTypeFamily::INTEGER,
            'FLOAT', 'FLOAT4', 'REAL' => ColumnTypeFamily::FLOAT,
            'DOUBLE', 'FLOAT8', 'DOUBLE PRECISION' => ColumnTypeFamily::DOUBLE,
            'DECIMAL', 'NEWDECIMAL', 'NUMERIC' => ColumnTypeFamily::DECIMAL,
            'CHAR', 'CHARACTER', 'BPCHAR', 'VARCHAR', 'CHARACTER VARYING', 'VAR_STRING', 'STRING', 'ENUM', 'SET',
            'NAME' => ColumnTypeFamily::STRING,
            'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT', 'CITEXT', 'CLOB' => ColumnTypeFamily::TEXT,
            'BOOL', 'BOOLEAN' => ColumnTypeFamily::BOOLEAN,
            'DATE', 'NEWDATE' => ColumnTypeFamily::DATE,
            'TIME', 'TIME2', 'TIMETZ', 'TIME WITH TIME ZONE', 'TIME WITHOUT TIME ZONE'
                => ColumnTypeFamily::TIME,
            'DATETIME', 'DATETIME2' => ColumnTypeFamily::DATETIME,
            'TIMESTAMP', 'TIMESTAMP2', 'TIMESTAMPTZ', 'TIMESTAMP WITH TIME ZONE',
            'TIMESTAMP WITHOUT TIME ZONE' => ColumnTypeFamily::TIMESTAMP,
            'BYTEA', 'BINARY', 'VARBINARY', 'BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB'
                => ColumnTypeFamily::BINARY,
            'JSON', 'JSONB' => ColumnTypeFamily::JSON,
            default => ColumnTypeFamily::UNKNOWN,
        };

        return new self($family, $nativeType);
    }

}
