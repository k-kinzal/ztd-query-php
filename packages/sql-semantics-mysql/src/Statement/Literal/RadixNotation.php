<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

/**
 * The quoted or numeric-prefix notation of a hexadecimal or bit literal.
 *
 * Quoted hexadecimal literals require pairs of digits; numeric prefixes
 * require at least one digit. The quoted prefix may use either letter case,
 * whereas the numeric prefixes are always `0x` and `0b`.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/hexadecimal-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/bit-value-literals.html.
 *
 * @visibility public
 * @example Retaining the notation independently of the literal's value
 *     \SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation::Prefixed->spell(\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal, '01AF') // => '0x01AF'
 */
enum RadixNotation
{
    case Quoted;
    case UpperQuoted;
    case Prefixed;

    /**
     * Chooses a valid notation when a caller supplies only the digit value.
     */
    public static function canonical(Radix $radix, string $digits): self
    {
        return $radix === Radix::Hexadecimal && strlen($digits) % 2 !== 0 ? self::Prefixed : self::Quoted;
    }

    /**
     * Checks the notation's digit-count requirement; the literal checks its digit alphabet.
     */
    public function permits(Radix $radix, string $digits): bool
    {
        return $this === self::Prefixed ? $digits !== '' : ($radix === Radix::Bit || strlen($digits) % 2 === 0);
    }

    /**
     * Writes the selected notation around the literal's digits.
     */
    public function spell(Radix $radix, string $digits): string
    {
        $prefix = $radix === Radix::Hexadecimal ? 'x' : 'b';

        return $this === self::Prefixed ? '0' . $prefix . $digits : ($this === self::UpperQuoted ? strtoupper($prefix) : $prefix) . "'" . $digits . "'";
    }
}
