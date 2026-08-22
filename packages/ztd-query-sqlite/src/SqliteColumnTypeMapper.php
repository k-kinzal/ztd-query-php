<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

final class SqliteColumnTypeMapper
{
    public function map(string $nativeType): ColumnType
    {
        $upper = strtoupper($nativeType);
        $withoutParameters = preg_replace('/\(.*\)/', '', $upper);
        $baseType = trim(is_string($withoutParameters) ? $withoutParameters : $upper);

        $family = match ($baseType) {
            'INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT', 'INT2', 'INT8'
                => ColumnTypeFamily::INTEGER,
            'REAL', 'DOUBLE', 'DOUBLE PRECISION', 'FLOAT' => ColumnTypeFamily::FLOAT,
            'DECIMAL', 'NUMERIC' => ColumnTypeFamily::DECIMAL,
            'BOOLEAN', 'BOOL' => ColumnTypeFamily::BOOLEAN,
            'TEXT', 'CLOB' => ColumnTypeFamily::TEXT,
            'CHAR', 'CHARACTER', 'VARCHAR', 'VARYING CHARACTER', 'NCHAR',
            'NATIVE CHARACTER', 'NVARCHAR', 'STRING' => ColumnTypeFamily::STRING,
            'BLOB' => ColumnTypeFamily::BINARY,
            'DATE' => ColumnTypeFamily::DATE,
            'TIME' => ColumnTypeFamily::TIME,
            'DATETIME' => ColumnTypeFamily::DATETIME,
            'TIMESTAMP' => ColumnTypeFamily::TIMESTAMP,
            'JSON' => ColumnTypeFamily::JSON,
            default => ColumnTypeFamily::UNKNOWN,
        };

        return new ColumnType($family, $nativeType);
    }
}
