<?php

declare(strict_types=1);

namespace Deriver\Value;

/**
 * Implements checked scalar arithmetic for the declared 64-bit PHP target.
 * @visibility root
 */
final class Arithmetic
{
    /**
     * Applies arithmetic only after validating the scalar operand categories.
     * @param string $operator Arithmetic or bitwise operator
     * @param Term $left Concrete left operand
     * @param Term $right Concrete right operand
     * @return Term Concrete result or an explicit runtime exception
     */
    public function apply(string $operator, Term $left, Term $right): Term
    {
        $a = $left->literal;
        $b = $right->literal;
        $secret = $left->isSecret() || $right->isSecret();
        if (in_array($operator, ['&', '|', '^'], true) && is_string($a) && is_string($b)) {
            return Term::constant(match ($operator) {
                '&' => $a & $b, '|' => $a | $b, '^' => $a ^ $b
            }, $secret);
        }
        if ($operator === 'xor') {
            $truth = new Operations();
            return Term::constant($truth->truth($left) !== $truth->truth($right), $secret);
        }
        $a = $this->number($a);
        $b = $this->number($b);
        if ($a === null || $b === null) {
            return new Term('throwable', 'TypeError');
        }
        if (($operator === '/' && ($b <=> 0) === 0) || ($operator === '%' && (new IntegerConversion())->apply($b) === 0)) {
            return new Term('throwable', 'DivisionByZeroError');
        }
        if (($operator === '<<' || $operator === '>>') && (new IntegerConversion())->apply($b) < 0) {
            return new Term('throwable', 'ArithmeticError');
        }
        $result = $this->calculate($operator, $a, $b);
        return $result === null ? Term::opaque('UNSUPPORTED_LANGUAGE_FEATURE', dependencies: [$left, $right]) : Term::constant($result, $secret);
    }

    /**
     * Parses PHP arithmetic scalar inputs without executing application code.
     * @param scalar|null $value Scalar input
     * @return int|float|null Numeric operand; null means invalid arithmetic input
     */
    public function number(int|float|string|bool|null $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_bool($value) || $value === null) {
            return (int) $value;
        }
        return (new NumericString())->parse($value);
    }

    /**
     * Evaluates an already validated scalar operation.
     * @param string $operator Operator
     * @param int|float $a Left numeric operand
     * @param int|float $b Right numeric operand
     * @return int|float|null Result; null for an unsupported operator
     */
    public function calculate(string $operator, int|float $a, int|float $b): int|float|null
    {
        $integers = new IntegerConversion();
        return match ($operator) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
            '/' => $a / $b,
            '%' => $integers->apply($a) % $integers->apply($b),
            '**' => $this->power($a, $b),
            '&' => $integers->apply($a) & $integers->apply($b),
            '|' => $integers->apply($a) | $integers->apply($b),
            '^' => $integers->apply($a) ^ $integers->apply($b),
            '<<' => $integers->apply($b) >= 64 ? 0 : $integers->apply($a) << $integers->apply($b),
            '>>' => $integers->apply($b) >= 64 ? ($integers->apply($a) < 0 ? -1 : 0) : $integers->apply($a) >> $integers->apply($b),
            default => null,
        };
    }

    /**
     * Raises to a power without the zero-base deprecation that PHP 8.4 and later hosts emit.
     * @param int|float $base Numeric base
     * @param int|float $exponent Numeric exponent
     * @return int|float PHP 8.3 result; a zero base with a negative exponent yields a signed infinity
     */
    public function power(int|float $base, int|float $exponent): int|float
    {
        if (($base <=> 0) === 0 && $exponent < 0) {
            return fdiv(1, $base ** -$exponent);
        }
        return $base ** $exponent;
    }

    /**
     * Reports implicit integer-conversion diagnostics for integral operators.
     * @param string $operator Binary operator
     * @param Term $left Left scalar operand
     * @param Term $right Right scalar operand
     * @return bool Whether either numeric conversion loses precision
     */
    public function warning(string $operator, Term $left, Term $right): bool
    {
        if ($left->kind !== 'constant' || $right->kind !== 'constant' || !in_array($operator, ['%', '&', '|', '^', '<<', '>>'], true)) {
            return false;
        }
        if (in_array($operator, ['&', '|', '^'], true) && is_string($left->literal) && is_string($right->literal)) {
            return false;
        }
        $a = $this->number($left->literal);
        $b = $this->number($right->literal);
        return $a !== null && $b !== null && ((new IntegerConversion())->warning($a) || (new IntegerConversion())->warning($b));
    }
}
