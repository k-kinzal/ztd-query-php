<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Lexical;

/**
 * Reads the exact value of a PostgreSQL numeric constant token without floating point.
 *
 * Rule: PG-LEX-NUMBER-001. Scope: the `ICONST` and `FCONST` terminals. A
 * constant written as an integer, in decimal, hexadecimal (`0x`), octal
 * (`0o`) or binary (`0b`) digits with optional underscores between digits, is
 * the integer it denotes whatever its size; the scanner only chooses the
 * terminal by whether the value fits 32 bits. A constant written with a
 * decimal point or an exponent keeps its integer digits, fraction digits and
 * exponent, because the fraction length is the display scale of the numeric
 * value. Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-CONSTANTS-NUMERIC.
 * Termination: one pass over the digits per conversion step.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Numerals
{
    /**
     * Tells whether a numeric token is written as an integer.
     */
    public function integral(string $text): bool
    {
        return preg_match('/\A(?:0[xXoObB][0-9A-Fa-f_]+|[0-9_]+)\z/', $text) === 1;
    }

    /**
     * Answers the canonical decimal digits of a constant written as an integer.
     */
    public function decimal(string $text): string
    {
        $digits = str_replace('_', '', $text);
        $base = ['0x' => 16, '0o' => 8, '0b' => 2][strtolower(substr($digits, 0, 2))] ?? 10;
        if ($base === 10) {
            return $this->canonical($digits);
        }
        $limbs = [0];
        foreach (str_split(substr($digits, 2)) as $digit) {
            $carry = (int) hexdec($digit);
            foreach ($limbs as $position => $limb) {
                $product = $limb * $base + $carry;
                $limbs[$position] = $product % 10000000;
                $carry = intdiv($product, 10000000);
            }
            if ($carry > 0) {
                $limbs[] = $carry;
            }
        }
        $decimal = '';
        foreach ($limbs as $limb) {
            $decimal = str_pad((string) $limb, 7, '0', STR_PAD_LEFT) . $decimal;
        }

        return $this->canonical($decimal);
    }

    /**
     * Answers the integer digits, fraction digits and exponent of a constant written with a point or an exponent.
     *
     * @return array{string, string, string|null}
     */
    public function parts(string $text): array
    {
        $digits = str_replace('_', '', $text);
        $mantissa = $digits;
        $exponent = null;
        $mark = strcspn($digits, 'eE');
        if ($mark < strlen($digits)) {
            $mantissa = substr($digits, 0, $mark);
            $written = substr($digits, $mark + 1);
            $magnitude = $this->canonical(ltrim($written, '+-'));
            $exponent = $magnitude === '0' ? null : (str_starts_with($written, '-') ? '-' : '') . $magnitude;
        }
        $point = strpos($mantissa, '.');
        if ($point === false) {
            return [$this->canonical($mantissa), '', $exponent];
        }

        return [$this->canonical(substr($mantissa, 0, $point)), substr($mantissa, $point + 1), $exponent];
    }

    /**
     * Strips leading zeros from decimal digits; no digits denote zero.
     */
    public function canonical(string $digits): string
    {
        $stripped = ltrim($digits, '0');

        return $stripped === '' ? '0' : $stripped;
    }

    /**
     * Tells whether canonical decimal digits denote a value not above a canonical limit.
     */
    public function within(string $digits, string $limit): bool
    {
        return strlen($digits) < strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) <= 0);
    }
}
