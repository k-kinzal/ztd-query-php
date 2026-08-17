<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use ZtdQuery\Schema\ColumnTypeFamily;

final class MysqliFieldTypeProvider
{
    /** @return iterable<string, array{int, int|string, ColumnTypeFamily}> */
    public static function provide(): iterable
    {
        foreach ([
            MYSQLI_TYPE_TINY,
            MYSQLI_TYPE_SHORT,
            MYSQLI_TYPE_LONG,
            MYSQLI_TYPE_LONGLONG,
            MYSQLI_TYPE_INT24,
            MYSQLI_TYPE_YEAR,
            MYSQLI_TYPE_BIT,
        ] as $type) {
            yield "integer $type" => [$type, 255, ColumnTypeFamily::INTEGER];
        }
        yield 'float' => [MYSQLI_TYPE_FLOAT, 255, ColumnTypeFamily::FLOAT];
        yield 'double' => [MYSQLI_TYPE_DOUBLE, 255, ColumnTypeFamily::DOUBLE];
        yield 'decimal' => [MYSQLI_TYPE_DECIMAL, 255, ColumnTypeFamily::DECIMAL];
        yield 'new decimal' => [MYSQLI_TYPE_NEWDECIMAL, 255, ColumnTypeFamily::DECIMAL];
        yield 'date' => [MYSQLI_TYPE_DATE, 255, ColumnTypeFamily::DATE];
        yield 'new date' => [MYSQLI_TYPE_NEWDATE, 255, ColumnTypeFamily::DATE];
        yield 'time' => [MYSQLI_TYPE_TIME, 255, ColumnTypeFamily::TIME];
        yield 'datetime' => [MYSQLI_TYPE_DATETIME, 255, ColumnTypeFamily::DATETIME];
        yield 'timestamp' => [MYSQLI_TYPE_TIMESTAMP, 255, ColumnTypeFamily::TIMESTAMP];
        yield 'json' => [MYSQLI_TYPE_JSON, 255, ColumnTypeFamily::JSON];
        foreach ([
            MYSQLI_TYPE_TINY_BLOB,
            MYSQLI_TYPE_MEDIUM_BLOB,
            MYSQLI_TYPE_LONG_BLOB,
            MYSQLI_TYPE_BLOB,
        ] as $type) {
            yield "binary $type" => [$type, 63, ColumnTypeFamily::BINARY];
        }
        yield 'text blob' => [MYSQLI_TYPE_BLOB, 255, ColumnTypeFamily::TEXT];
        foreach ([MYSQLI_TYPE_VAR_STRING, MYSQLI_TYPE_STRING, MYSQLI_TYPE_ENUM, MYSQLI_TYPE_SET] as $type) {
            yield "string $type" => [$type, 255, ColumnTypeFamily::STRING];
        }
        yield 'unknown' => [MYSQLI_TYPE_NULL, 255, ColumnTypeFamily::UNKNOWN];
    }
}
