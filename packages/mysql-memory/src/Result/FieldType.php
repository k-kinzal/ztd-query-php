<?php

declare(strict_types=1);

namespace MySqlMemory\Result;

/**
 * The type code of a result column, as the protocol sends it in a column definition.
 *
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/field__types_8h.html.
 *
 * @visibility public
 * @example Reading the code of a type
 *     \MySqlMemory\Result\FieldType::LongLong->value // => 8
 */
enum FieldType: int
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
     * Tells whether the binary protocol sends a value of this type as an integer.
     *
     * @example Integers and years
     *     [\MySqlMemory\Result\FieldType::Year->integral(), \MySqlMemory\Result\FieldType::Double->integral()] // => [true, false]
     */
    public function integral(): bool
    {
        return in_array($this, [self::Tiny, self::Short, self::Long, self::LongLong, self::Int24, self::Year], true);
    }

    /**
     * Tells whether the binary protocol sends a value of this type as a date or a time.
     *
     * @example Temporal types
     *     [\MySqlMemory\Result\FieldType::DateTime->temporal(), \MySqlMemory\Result\FieldType::Year->temporal()] // => [true, false]
     */
    public function temporal(): bool
    {
        return in_array($this, [self::Date, self::NewDate, self::DateTime, self::Timestamp, self::Time], true);
    }
}
