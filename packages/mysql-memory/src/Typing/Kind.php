<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

/**
 * How the emulator holds a value of a domain at run time.
 *
 * An Integer is a PHP int, read as unsigned when its domain is UNSIGNED; a Decimal is the
 * canonical text of the exact number with the scale of its domain; a Double is a PHP float; a
 * String is the bytes in the character set of its collation; the temporal kinds are their
 * canonical text (`2024-01-31`, `-838:59:59.000000`, `2024-01-31 12:00:00`); a Json is the
 * canonical JSON text; Null holds only NULL.
 *
 * @visibility public
 * @example Numeric kinds
 *     [\MySqlMemory\Typing\Kind::Decimal->numeric(), \MySqlMemory\Typing\Kind::String->numeric()] // => [true, false]
 */
enum Kind
{
    case Integer;
    case Decimal;
    case Double;
    case String;
    case Date;
    case Time;
    case DateTime;
    case Year;
    case Json;
    case Bit;
    case Null;

    /**
     * Tells whether values of the kind are numbers.
     */
    public function numeric(): bool
    {
        return $this === self::Integer || $this === self::Decimal || $this === self::Double || $this === self::Year || $this === self::Bit;
    }

    /**
     * Tells whether values of the kind are dates or times.
     *
     * @example A date and a year
     *     [\MySqlMemory\Typing\Kind::Date->temporal(), \MySqlMemory\Typing\Kind::Year->temporal()] // => [true, false]
     */
    public function temporal(): bool
    {
        return $this === self::Date || $this === self::Time || $this === self::DateTime;
    }
}
