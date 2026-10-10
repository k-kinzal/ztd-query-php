<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

/**
 * The type of a JSON value: the types of a JSON text, and the types an SQL value keeps when it becomes a JSON value.
 *
 * An integer of a JSON text above the largest signed 64-bit integer, and an unsigned integer or a
 * YEAR made a JSON value, is an unsigned integer; a TIMESTAMP keeps its type apart from a DATETIME
 * (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html ("Comparison and Ordering of JSON Values").
 *
 * @visibility MySqlMemory
 */
enum JsonKind
{
    case Null;
    case Boolean;
    case Integer;
    case Unsigned;
    case Double;
    case Decimal;
    case String;
    case Array;
    case Object;
    case Date;
    case Time;
    case DateTime;
    case Timestamp;
    case Opaque;

    /**
     * Tells whether values of the type are numbers, which compare with each other by value.
     *
     * @example A double
     *     \MySqlMemory\Value\Json\JsonKind::Double->numeric() // => true
     */
    public function numeric(): bool
    {
        return match ($this) {
            self::Integer, self::Unsigned, self::Double, self::Decimal => true,
            self::Null, self::Boolean, self::String, self::Array, self::Object, self::Date, self::Time, self::DateTime, self::Timestamp, self::Opaque => false,
        };
    }

    /**
     * Tells whether values of the type are written as JSON strings but keep their type: a temporal or an opaque value.
     *
     * @example A date
     *     \MySqlMemory\Value\Json\JsonKind::Date->quoted() // => true
     */
    public function quoted(): bool
    {
        return match ($this) {
            self::Date, self::Time, self::DateTime, self::Timestamp, self::Opaque => true,
            self::Null, self::Boolean, self::Integer, self::Unsigned, self::Double, self::Decimal, self::String, self::Array, self::Object => false,
        };
    }

    /**
     * Answers the rank of the type in the order of JSON values of different types: NULL lowest,
     * then numbers, strings, objects, arrays, booleans, dates, times, datetimes and timestamps,
     * and opaque values highest.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html ("Comparison and Ordering of JSON Values").
     *
     * @example A boolean ranks above an array
     *     \MySqlMemory\Value\Json\JsonKind::Boolean->rank() > \MySqlMemory\Value\Json\JsonKind::Array->rank() // => true
     */
    public function rank(): int
    {
        return match ($this) {
            self::Null => 0,
            self::Integer, self::Unsigned, self::Double, self::Decimal => 1,
            self::String => 2,
            self::Object => 3,
            self::Array => 4,
            self::Boolean => 5,
            self::Date => 6,
            self::Time => 7,
            self::DateTime, self::Timestamp => 8,
            self::Opaque => 9,
        };
    }

    /**
     * Answers the type a letter of the typed text of a document marks.
     *
     * @example A decimal
     *     \MySqlMemory\Value\Json\JsonKind::marked('d') // => \MySqlMemory\Value\Json\JsonKind::Decimal
     */
    public static function marked(string $letter): self
    {
        return match ($letter) {
            'u' => self::Unsigned,
            'd' => self::Decimal,
            'D' => self::Date,
            'T' => self::Time,
            'S' => self::DateTime,
            'P' => self::Timestamp,
            default => self::Opaque,
        };
    }

    /**
     * Answers the letter that marks a value of the type in the typed text of a document, or an empty string for a type its JSON text tells.
     *
     * @example A decimal
     *     \MySqlMemory\Value\Json\JsonKind::Decimal->mark() // => 'd'
     */
    public function mark(): string
    {
        return match ($this) {
            self::Unsigned => 'u',
            self::Decimal => 'd',
            self::Date => 'D',
            self::Time => 'T',
            self::DateTime => 'S',
            self::Timestamp => 'P',
            self::Opaque => 'O',
            self::Null, self::Boolean, self::Integer, self::Double, self::String, self::Array, self::Object => '',
        };
    }
}
