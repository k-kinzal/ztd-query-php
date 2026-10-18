<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Scalar;

/**
 * Implements checked scalar arithmetic for the declared 64-bit PHP target.
 * @visibility root
 */
final class Arithmetic
{
    /**
     * Applies arithmetic only after validating the scalar operand categories.
     * @param string $operator Arithmetic or bitwise operator
     * @param \Deriver\Deriver\Evaluator\Term $left Concrete left operand
     * @param \Deriver\Deriver\Evaluator\Term $right Concrete right operand
     * @return \Deriver\Deriver\Evaluator\Term Concrete result or an explicit runtime exception
     */
    public function apply(string $operator, \Deriver\Deriver\Evaluator\Term $left, \Deriver\Deriver\Evaluator\Term $right): \Deriver\Deriver\Evaluator\Term
    {
        $a = $left->literal;
        $b = $right->literal;
        if (in_array($operator, ['&', '|', '^'], true) && is_string($a) && is_string($b)) {
            return \Deriver\Deriver\Evaluator\Term::constant(match ($operator) {
                '&' => $a & $b, '|' => $a | $b, '^' => $a ^ $b
            });
        }
        if ($operator === 'xor') {
            $truth = new Operations();
            return \Deriver\Deriver\Evaluator\Term::constant($truth->truth($left) !== $truth->truth($right));
        }
        $a = $this->number($a);
        $b = $this->number($b);
        if ($a === null || $b === null) {
            return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'TypeError');
        }
        if (($operator === '/' && ($b <=> 0) === 0) || ($operator === '%' && (new IntegerConversion())->apply($b) === 0)) {
            return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'DivisionByZeroError');
        }
        if (($operator === '<<' || $operator === '>>') && (new IntegerConversion())->apply($b) < 0) {
            return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'ArithmeticError');
        }
        $result = $this->calculate($operator, $a, $b);
        return $result === null ? \Deriver\Deriver\Evaluator\Term::opaque('UNSUPPORTED_LANGUAGE_FEATURE', dependencies: [$left, $right]) : \Deriver\Deriver\Evaluator\Term::constant($result);
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
     * @param \Deriver\Deriver\Evaluator\Term $left Left scalar operand
     * @param \Deriver\Deriver\Evaluator\Term $right Right scalar operand
     * @return bool Whether either numeric conversion loses precision
     */
    public function warning(string $operator, \Deriver\Deriver\Evaluator\Term $left, \Deriver\Deriver\Evaluator\Term $right): bool
    {
        if ($left->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || $right->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || !in_array($operator, ['%', '&', '|', '^', '<<', '>>'], true)) {
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
