<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing;

use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * Types the arithmetic, concatenation and bitwise catalog operators over base types.
 *
 * Rule: PG-ARITHMETIC-TYPING-001. `+ - * /` over numbers give the wider of
 * two integer types, `numeric` with an integer and `numeric`, `double
 * precision` once a floating-point type meets another type, and the type
 * itself for two equal types; `%` is defined for integers and `numeric`;
 * `^` for `double precision` and `numeric`, integers reaching it as `double
 * precision`; an interval is scaled by a number with `*` and `/`; `||` gives
 * `text` when a side is a string; the bitwise operators keep the integer
 * type, a shift the type of its left operand. These follow from the catalog
 * operators and the numeric promotion of operator resolution. Termination:
 * constant work.
 * Source: https://www.postgresql.org/docs/17/functions-math.html, https://www.postgresql.org/docs/17/typeconv-oper.html,
 * https://www.postgresql.org/docs/17/functions-string.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Arithmetic
{
    /**
     * The integer types, narrowest first.
     */
    public const INTEGERS = [Builtin::Int2, Builtin::Int4, Builtin::Int8];

    /**
     * Types the arithmetic operators over numbers, and the interval scaling by a number.
     */
    public function arithmetic(string $operator, Builtin $left, Builtin $right): ?Builtin
    {
        $categories = new Categories();
        if ($left === Builtin::Interval || $right === Builtin::Interval) {
            return $this->scaling($operator, $left, $right);
        }
        if (!$categories->numeric($left) || !$categories->numeric($right) || !in_array($operator, ['+', '-', '*', '/', '%', '^'], true)) {
            return null;
        }
        $integers = in_array($left, self::INTEGERS, true) && in_array($right, self::INTEGERS, true);
        $floating = in_array($left, [Builtin::Float4, Builtin::Float8], true) || in_array($right, [Builtin::Float4, Builtin::Float8], true);
        if ($operator === '^') {
            return $floating || $integers ? Builtin::Float8 : Builtin::Numeric;
        }
        if ($integers) {
            return array_search($left, self::INTEGERS, true) >= array_search($right, self::INTEGERS, true) ? $left : $right;
        }
        if ($operator === '%') {
            return $floating ? null : Builtin::Numeric;
        }
        if ($left === $right) {
            return $left;
        }

        return $floating ? Builtin::Float8 : Builtin::Numeric;
    }

    /**
     * Types the scaling of an interval by a number: `interval * n`, `n * interval`, `interval / n`.
     */
    public function scaling(string $operator, Builtin $left, Builtin $right): ?Builtin
    {
        $categories = new Categories();
        $scaled = $left === Builtin::Interval ? $categories->numeric($right) && ($operator === '*' || $operator === '/') : $categories->numeric($left) && $operator === '*';

        return $scaled ? Builtin::Interval : null;
    }

    /**
     * Types `||` with a string on one side: `text`.
     */
    public function concatenation(string $operator, Builtin $left, Builtin $right): ?Builtin
    {
        $categories = new Categories();
        if ($operator !== '||') {
            return null;
        }

        return $categories->textual($left) || $categories->textual($right) ? Builtin::Text : null;
    }

    /**
     * Types the bitwise operators over integers: the type of the left operand for shifts, the wider type otherwise.
     */
    public function bitwise(string $operator, Builtin $left, Builtin $right): ?Builtin
    {
        if (!in_array($left, self::INTEGERS, true) || !in_array($right, self::INTEGERS, true)) {
            return null;
        }
        if ($operator === '<<' || $operator === '>>') {
            return $right === Builtin::Int8 ? null : $left;
        }

        return in_array($operator, ['&', '|', '#'], true) ? $this->arithmetic('+', $left, $right) : null;
    }
}
