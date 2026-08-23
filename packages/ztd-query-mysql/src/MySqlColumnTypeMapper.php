<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

final class MySqlColumnTypeMapper
{
    public function map(string $nativeType): ColumnType
    {
        $normalized = strtoupper(trim($nativeType));
        $baseType = substr($normalized, 0, strcspn($normalized, "( \t\r\n"));

        $family = match ($baseType) {
            'INT', 'INTEGER', 'TINY', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT',
            'LONG', 'LONGLONG', 'SHORT', 'INT24', 'YEAR', 'BIT' => ColumnTypeFamily::INTEGER,
            'FLOAT' => ColumnTypeFamily::FLOAT,
            'DOUBLE', 'REAL' => ColumnTypeFamily::DOUBLE,
            'DECIMAL', 'NEWDECIMAL', 'NUMERIC' => ColumnTypeFamily::DECIMAL,
            'CHAR', 'VARCHAR', 'VAR_STRING', 'STRING', 'ENUM', 'SET' => ColumnTypeFamily::STRING,
            'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT' => ColumnTypeFamily::TEXT,
            'BOOL', 'BOOLEAN' => ColumnTypeFamily::BOOLEAN,
            'DATE', 'NEWDATE' => ColumnTypeFamily::DATE,
            'TIME', 'TIME2' => ColumnTypeFamily::TIME,
            'DATETIME', 'DATETIME2' => ColumnTypeFamily::DATETIME,
            'TIMESTAMP', 'TIMESTAMP2' => ColumnTypeFamily::TIMESTAMP,
            'BINARY', 'VARBINARY', 'BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB',
            'TINY_BLOB', 'MEDIUM_BLOB', 'LONG_BLOB', 'VECTOR'
                => ColumnTypeFamily::BINARY,
            'JSON' => ColumnTypeFamily::JSON,
            default => ColumnTypeFamily::UNKNOWN,
        };

        return new ColumnType($family, $nativeType);
    }
}
