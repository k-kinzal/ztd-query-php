<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

final class PgSqlColumnTypeMapper
{
    public function map(string $nativeType): ColumnType
    {
        $upper = strtoupper($nativeType);
        $withoutParameters = preg_replace('/\(.*\)/', '', $upper);
        $baseType = trim(is_string($withoutParameters) ? $withoutParameters : $upper);
        $withoutArrays = preg_replace('/(?:\[\s*\])+$/', '', $baseType);
        $baseType = trim(is_string($withoutArrays) ? $withoutArrays : $baseType);

        $family = match ($baseType) {
            'INT', 'INT2', 'INT4', 'INT8', 'INTEGER', 'SMALLINT', 'BIGINT',
            'SERIAL', 'SMALLSERIAL', 'BIGSERIAL' => ColumnTypeFamily::INTEGER,
            'REAL', 'FLOAT4' => ColumnTypeFamily::FLOAT,
            'DOUBLE PRECISION', 'FLOAT8' => ColumnTypeFamily::DOUBLE,
            'DECIMAL', 'NUMERIC' => ColumnTypeFamily::DECIMAL,
            'CHAR', 'CHARACTER', 'BPCHAR', 'VARCHAR', 'CHARACTER VARYING', 'NAME'
                => ColumnTypeFamily::STRING,
            'TEXT', 'CITEXT' => ColumnTypeFamily::TEXT,
            'BOOLEAN', 'BOOL' => ColumnTypeFamily::BOOLEAN,
            'DATE' => ColumnTypeFamily::DATE,
            'TIME', 'TIMETZ', 'TIME WITH TIME ZONE', 'TIME WITHOUT TIME ZONE'
                => ColumnTypeFamily::TIME,
            'TIMESTAMP', 'TIMESTAMPTZ', 'TIMESTAMP WITH TIME ZONE',
            'TIMESTAMP WITHOUT TIME ZONE' => ColumnTypeFamily::TIMESTAMP,
            'BYTEA' => ColumnTypeFamily::BINARY,
            'JSON', 'JSONB' => ColumnTypeFamily::JSON,
            default => ColumnTypeFamily::UNKNOWN,
        };

        return new ColumnType($family, $nativeType);
    }
}
