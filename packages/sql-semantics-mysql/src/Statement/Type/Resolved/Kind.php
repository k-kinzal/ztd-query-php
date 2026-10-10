<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Resolved;

/**
 * The class of values a resolved type holds, which decides how the server compares and converts them.
 *
 * @visibility public
 * @example Asking whether a kind is numeric
 *     \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Decimal->numeric() // => true
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
     * Answers whether values of the kind are numbers.
     */
    public function numeric(): bool
    {
        return $this === self::Integer || $this === self::Decimal || $this === self::Double || $this === self::Year || $this === self::Bit;
    }

    /**
     * Answers whether values of the kind are dates, times or datetimes.
     */
    public function temporal(): bool
    {
        return $this === self::Date || $this === self::Time || $this === self::DateTime;
    }
}
