<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Integer;

/**
 * The type of a JSON value: the types of a JSON text, and the types an SQL value keeps when it becomes a JSON value.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html ("Comparison and Ordering of JSON Values").
 *
 * @visibility MySqlMemory
 */
enum JsonKind
{
    case Null;
    case Boolean;
    case Integer;
    case Double;
    case Decimal;
    case String;
    case Array;
    case Object;
    case Date;
    case Time;
    case DateTime;
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
            self::Integer, self::Double, self::Decimal => true,
            self::Null, self::Boolean, self::String, self::Array, self::Object, self::Date, self::Time, self::DateTime, self::Opaque => false,
        };
    }
}
