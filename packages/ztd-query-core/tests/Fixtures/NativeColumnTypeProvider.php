<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use ZtdQuery\Schema\ColumnTypeFamily;

final class NativeColumnTypeProvider
{
    /** @return iterable<string, array{string, ColumnTypeFamily}> */
    public static function provide(): iterable
    {
        foreach ([
            'INT', 'INT2', 'INT4', 'INT8', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT',
            'SERIAL', 'SMALLSERIAL', 'BIGSERIAL', 'LONG', 'LONGLONG', 'SHORT', 'INT24', 'YEAR', 'BIT',
            ' int8[] ',
        ] as $type) {
            yield "integer $type" => [$type, ColumnTypeFamily::INTEGER];
        }
        foreach (['FLOAT', 'FLOAT4', 'REAL'] as $type) {
            yield "float $type" => [$type, ColumnTypeFamily::FLOAT];
        }
        foreach (['DOUBLE', 'FLOAT8', 'DOUBLE PRECISION'] as $type) {
            yield "double $type" => [$type, ColumnTypeFamily::DOUBLE];
        }
        foreach (['DECIMAL', 'NEWDECIMAL', 'NUMERIC', 'numeric(10, 2)'] as $type) {
            yield "decimal $type" => [$type, ColumnTypeFamily::DECIMAL];
        }
        foreach ([
            'CHAR', 'CHARACTER', 'BPCHAR', 'VARCHAR', 'CHARACTER VARYING', 'VAR_STRING', 'STRING',
            'ENUM', 'SET', 'NAME',
        ] as $type) {
            yield "string $type" => [$type, ColumnTypeFamily::STRING];
        }
        foreach (['TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT', 'CITEXT', 'CLOB'] as $type) {
            yield "text $type" => [$type, ColumnTypeFamily::TEXT];
        }
        foreach (['BOOL', 'BOOLEAN'] as $type) {
            yield "boolean $type" => [$type, ColumnTypeFamily::BOOLEAN];
        }
        foreach (['DATE', 'NEWDATE'] as $type) {
            yield "date $type" => [$type, ColumnTypeFamily::DATE];
        }
        foreach (['TIME', 'TIME2', 'TIMETZ', 'TIME WITH TIME ZONE', 'TIME WITHOUT TIME ZONE'] as $type) {
            yield "time $type" => [$type, ColumnTypeFamily::TIME];
        }
        foreach (['DATETIME', 'DATETIME2'] as $type) {
            yield "datetime $type" => [$type, ColumnTypeFamily::DATETIME];
        }
        foreach ([
            'TIMESTAMP', 'TIMESTAMP2', 'TIMESTAMPTZ', 'TIMESTAMP WITH TIME ZONE',
            'TIMESTAMP WITHOUT TIME ZONE',
        ] as $type) {
            yield "timestamp $type" => [$type, ColumnTypeFamily::TIMESTAMP];
        }
        foreach (['BYTEA', 'BINARY', 'VARBINARY', 'BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB'] as $type) {
            yield "binary $type" => [$type, ColumnTypeFamily::BINARY];
        }
        foreach (['JSON', 'JSONB'] as $type) {
            yield "json $type" => [$type, ColumnTypeFamily::JSON];
        }
        yield 'unknown native type' => ['custom_domain', ColumnTypeFamily::UNKNOWN];
    }
}
