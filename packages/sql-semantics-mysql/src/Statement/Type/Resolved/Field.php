<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Resolved;

/**
 * The column type code the server reports for a resolved type in result metadata.
 *
 * Each case holds the code of the client/server protocol.
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_query_response_text_resultset_column_definition.html.
 *
 * @visibility public
 * @example Reading the protocol code of BIGINT
 *     \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::LongLong->value // => 8
 */
enum Field: int
{
    case Decimal = 0;
    case Tiny = 1;
    case Short = 2;
    case Long = 3;
    case Float = 4;
    case Double = 5;
    case Null = 6;
    case Timestamp = 7;
    case LongLong = 8;
    case Int24 = 9;
    case Date = 10;
    case Time = 11;
    case DateTime = 12;
    case Year = 13;
    case NewDate = 14;
    case VarChar = 15;
    case Bit = 16;
    case Vector = 242;
    case Json = 245;
    case NewDecimal = 246;
    case Enum = 247;
    case Set = 248;
    case TinyBlob = 249;
    case MediumBlob = 250;
    case LongBlob = 251;
    case Blob = 252;
    case VarString = 253;
    case String = 254;
    case Geometry = 255;

    /**
     * Answers whether the code is an integer type, YEAR included.
     */
    public function integral(): bool
    {
        return in_array($this, [self::Tiny, self::Short, self::Long, self::LongLong, self::Int24, self::Year], true);
    }

    /**
     * Answers whether the code is a date, time, datetime or timestamp type.
     */
    public function temporal(): bool
    {
        return in_array($this, [self::Date, self::NewDate, self::DateTime, self::Timestamp, self::Time], true);
    }

    /**
     * Answers whether the code is a BLOB or TEXT type, JSON included.
     */
    public function blob(): bool
    {
        return in_array($this, [self::Blob, self::TinyBlob, self::MediumBlob, self::LongBlob, self::Json], true);
    }
}
