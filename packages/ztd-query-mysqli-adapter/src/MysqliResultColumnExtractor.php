<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli_result;
use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Schema\ColumnType;

final class MysqliResultColumnExtractor
{
    /** @return list<ResultColumn> */
    public static function extract(mysqli_result $result): array
    {
        $columns = [];
        foreach ($result->fetch_fields() as $field) {
            $nativeType = match ($field->type) {
                MYSQLI_TYPE_TINY => 'TINYINT',
                MYSQLI_TYPE_SHORT => 'SMALLINT',
                MYSQLI_TYPE_LONG => 'INTEGER',
                MYSQLI_TYPE_LONGLONG => 'BIGINT',
                MYSQLI_TYPE_INT24 => 'MEDIUMINT',
                MYSQLI_TYPE_YEAR => 'YEAR',
                MYSQLI_TYPE_BIT => 'BIT',
                MYSQLI_TYPE_FLOAT => 'FLOAT',
                MYSQLI_TYPE_DOUBLE => 'DOUBLE',
                MYSQLI_TYPE_DECIMAL, MYSQLI_TYPE_NEWDECIMAL => 'DECIMAL',
                MYSQLI_TYPE_DATE, MYSQLI_TYPE_NEWDATE => 'DATE',
                MYSQLI_TYPE_TIME => 'TIME',
                MYSQLI_TYPE_DATETIME => 'DATETIME',
                MYSQLI_TYPE_TIMESTAMP => 'TIMESTAMP',
                MYSQLI_TYPE_JSON => 'JSON',
                MYSQLI_TYPE_TINY_BLOB, MYSQLI_TYPE_MEDIUM_BLOB, MYSQLI_TYPE_LONG_BLOB,
                MYSQLI_TYPE_BLOB => self::isBinaryCharset($field->charsetnr) ? 'BLOB' : 'TEXT',
                MYSQLI_TYPE_VAR_STRING, MYSQLI_TYPE_STRING,
                MYSQLI_TYPE_ENUM, MYSQLI_TYPE_SET => 'VARCHAR',
                default => '',
            };
            $columns[] = new ResultColumn($field->name, ColumnType::fromNativeType($nativeType));
        }

        return $columns;
    }

    private static function isBinaryCharset(int|string $charsetNumber): bool
    {
        return $charsetNumber === 63 || $charsetNumber === '63';
    }
}
