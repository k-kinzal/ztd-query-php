<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Literal;

/**
 * An exact nonnegative integer, independent of machine integer or floating-point limits.
 * @visibility public
 * @example Decoding every bit of an unsigned 64-bit value
 *     (new \SqlSemantics\Statement\Literal\UnsignedInteger('ffffffffffffffff', \SqlSemantics\Statement\Literal\Radix::Hexadecimal))->decimal() // => '18446744073709551615'
 */
final class UnsignedInteger
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains digit grouping without allowing signs, exponents, or arbitrary SQL text.
     */
    public function __construct(public readonly string $digits, public readonly Radix $radix = Radix::Decimal)
    {
        $alphabet = substr('0123456789abcdef', 0, $radix->value);
        $ungrouped = str_replace('_', '', $digits);
        \SqlSemantics\Statement\Validation\Check::input($digits !== '' && strspn(strtolower($ungrouped), $alphabet) === strlen($ungrouped), 'Integer digits must belong to their numerical base.');
        \SqlSemantics\Statement\Validation\Check::input(!str_starts_with($digits, '_') && !str_ends_with($digits, '_') && !str_contains($digits, '__'), 'Integer separators must occur singly between digits.');
    }

    /**
     * Converts to canonical decimal digits without rounding or overflow.
     */
    public function decimal(): string
    {
        $digits = str_replace('_', '', $this->digits);
        if ($this->radix === Radix::Decimal) {
            $trimmed = ltrim($digits, '0');
            return $trimmed === '' ? '0' : $trimmed;
        }
        $decimal = '0';
        foreach (str_split($digits) as $digit) {
            $carry = (int) hexdec($digit);
            $next = '';
            for ($position = strlen($decimal) - 1; $position >= 0; --$position) {
                $part = (int) $decimal[$position] * $this->radix->value + $carry;
                $next = (string) ($part % 10) . $next;
                $carry = intdiv($part, 10);
            }
            $decimal = ($carry === 0 ? '' : (string) $carry) . $next;
        }
        $trimmed = ltrim($decimal, '0');
        return $trimmed === '' ? '0' : $trimmed;
    }

    /**
     * Adds one in the exact integer domain, returning a new decimal representation.
     */
    public function successor(): self
    {
        $digits = $this->decimal();
        for ($position = strlen($digits) - 1; $position >= 0; --$position) {
            if ($digits[$position] !== '9') {
                $digits[$position] = (string) ((int) $digits[$position] + 1);
                return new self($digits);
            }
            $digits[$position] = '0';
        }
        return new self('1' . $digits);
    }
}
