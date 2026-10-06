<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Lexical;

/**
 * Reads the exact value of a PostgreSQL numeric constant token without floating point.
 *
 * Rule: PG-LEX-NUMBER-001. Scope: the `ICONST` and `FCONST` terminals. A
 * constant written as an integer, in decimal, hexadecimal (`0x`), octal
 * (`0o`) or binary (`0b`) digits with optional underscores between digits, is
 * the integer it denotes whatever its size. The scanner makes it an `ICONST`
 * holding that integer when it fits 32 bits (`process_integer_literal` in
 * `scan.l`); otherwise, and for every constant written with a decimal point
 * or an exponent, it makes an `FCONST` that holds the written text itself
 * (a `T_Float` node keeps the string), because the parser does not yet know
 * the type the value is read as. Commands such as `SET`, storage parameters
 * and trigger arguments receive that text verbatim, so `1e2` and `100.` are
 * different values there. Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-CONSTANTS-NUMERIC,
 * `process_integer_literal` and the `{numeric}`, `{real}` rules of `src/backend/parser/scan.l` of PostgreSQL 17.
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
     * Answers the canonical decimal digits of the integer a constant written as an integer denotes, or null for one with a point or an exponent.
     */
    public function integer(string $text): ?string
    {
        return $this->integral($text) ? $this->decimal($text) : null;
    }

    /**
     * Tells whether a text is one constant the scanner reads as an `FCONST` and keeps as written.
     *
     * That is a decimal number with a point, an exponent or both, or an
     * integer spelling whose value does not fit 32 bits.
     */
    public function kept(string $text): bool
    {
        $digits = '[0-9](?:_?[0-9])*';
        if (preg_match('/\A(?:0[xX](?:_?[0-9A-Fa-f])+|0[oO](?:_?[0-7])+|0[bB](?:_?[01])+|' . $digits . ')\z/', $text) === 1) {
            return !$this->within($this->decimal($text), '2147483647');
        }

        return preg_match('/\A(?:' . $digits . '(?:\.(?:' . $digits . ')?)?|\.' . $digits . ')(?:[eE][-+]?' . $digits . ')?\z/', $text) === 1;
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
