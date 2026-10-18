<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Scalar;

/**
 * Pure PHP 8.3 scalar and array operations, independent of application execution.
 * @visibility root
 */
final class Operations
{
    /**
     * @param int|null $floatPrecision Captured target precision for float-to-string conversion; null when unknown
     */
    public function __construct(public readonly ?int $floatPrecision = null)
    {
    }

    /**
     * Converts a known or symbolic value to a PHP boolean without the NAN coercion warning of PHP 8.5 hosts.
     * @param \Deriver\Deriver\Evaluator\Term $value Input
     * @return bool|null Known truth value, or null when symbolic
     */
    public function truth(\Deriver\Deriver\Evaluator\Term $value): ?bool
    {
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Constant) {
            return is_float($value->literal) ? $value->literal !== 0.0 : (bool) $value->literal;
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Array) {
            return $value->operands !== [] ? true : (($value->attributes['open'] ?? false) === false ? false : null);
        }
        if (in_array($value->kind, [\Deriver\Deriver\Evaluator\TermKind::Object, \Deriver\Deriver\Evaluator\TermKind::Closure, \Deriver\Deriver\Evaluator\TermKind::Enum], true)) {
            return true;
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Uninitialized) {
            return false;
        }
        return null;
    }

    /**
     * Applies a type conversion while retaining symbolic input identity.
     * @param string $type Target scalar type
     * @param \Deriver\Deriver\Evaluator\Term $value Input
     * @return \Deriver\Deriver\Evaluator\Term Cast expression or concrete result
     */
    public function cast(string $type, \Deriver\Deriver\Evaluator\Term $value): \Deriver\Deriver\Evaluator\Term
    {
        $type = strtolower($type);
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Cast && $value->literal === $type) {
            return $value;
        }
        if ($type === 'bool') {
            $truth = $this->truth($value);
            return $truth === null ? new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Cast, 'bool', [$value], ['type' => 'bool']) : \Deriver\Deriver\Evaluator\Term::constant($truth);
        }
        if ($value->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant) {
            if ($type === 'string' && in_array($value->kind, [\Deriver\Deriver\Evaluator\TermKind::Array, \Deriver\Deriver\Evaluator\TermKind::Object], true)) {
                return \Deriver\Deriver\Evaluator\Term::opaque('UNSUPPORTED_LANGUAGE_FEATURE', 'string', [$value]);
            }
            return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Cast, $type, [$value], ['type' => $type]);
        }
        $native = $value->literal;
        if ($type === 'string') {
            if (is_float($native)) {
                $string = (new FloatConversion($this->floatPrecision))->string($native);
                return $string === null ? \Deriver\Deriver\Evaluator\Term::opaque('FLOAT_STRING_CONFIGURATION', 'string', [$value]) : \Deriver\Deriver\Evaluator\Term::constant($string);
            }
            return \Deriver\Deriver\Evaluator\Term::constant((string) $native);
        }
        if ($type === 'int') {
            if (is_float($native)) {
                return \Deriver\Deriver\Evaluator\Term::constant((new IntegerConversion())->apply($native));
            }
            return \Deriver\Deriver\Evaluator\Term::constant((int) $native);
        }
        if ($type === 'float' || $type === 'double') {
            return \Deriver\Deriver\Evaluator\Term::constant((float) $native);
        }
        if ($type === 'array') {
            return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Array, operands: $native === null ? [] : [\Deriver\Deriver\Evaluator\Term::constant($native)], attributes: ['open' => false]);
        }
        return \Deriver\Deriver\Evaluator\Term::opaque('UNSUPPORTED_LANGUAGE_FEATURE', $type, [$value]);
    }

    /**
     * Applies a binary operation without invoking user code.
     * @param string $operator PHP operator
     * @param \Deriver\Deriver\Evaluator\Term $left Left value
     * @param \Deriver\Deriver\Evaluator\Term $right Right value
     * @return \Deriver\Deriver\Evaluator\Term Constant, symbolic operation, or explicit throwable
     */
    public function binary(string $operator, \Deriver\Deriver\Evaluator\Term $left, \Deriver\Deriver\Evaluator\Term $right): \Deriver\Deriver\Evaluator\Term
    {
        if ($operator === '.') {
            $a = $this->cast('string', $left);
            $b = $this->cast('string', $right);
            if ($a->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && $b->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && is_string($a->literal) && is_string($b->literal)) {
                return \Deriver\Deriver\Evaluator\Term::constant($a->literal . $b->literal);
            }
            return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Concat, operands: [$a, $b], attributes: ['type' => 'string']);
        }
        if ($operator === 'xor') {
            $a = $this->truth($left);
            $b = $this->truth($right);
            return $a !== null && $b !== null ? \Deriver\Deriver\Evaluator\Term::constant($a !== $b) : new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Binary, 'xor', [$left, $right], ['type' => 'bool']);
        }
        if ($operator === '+' && $left->kind === \Deriver\Deriver\Evaluator\TermKind::Array && $right->kind === \Deriver\Deriver\Evaluator\TermKind::Array) {
            return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Array, operands: $left->operands + $right->operands, attributes: ['open' => ($left->attributes['open'] ?? false) === true || ($right->attributes['open'] ?? false) === true]);
        }
        if (in_array($operator, ['===', '!==', '==', '!=', '<', '<=', '>', '>=', '<=>'], true)) {
            return (new Comparison($this->floatPrecision))->apply($operator, $left, $right);
        }
        return $this->numeric($operator, $left, $right);
    }

    /**
     * Applies unary operators with explicit errors; signs multiply by -1 or 1 as PHP compiles them, keeping negative zero.
     * @param string $operator Parser-independent operator name
     * @param \Deriver\Deriver\Evaluator\Term $value Operand
     * @return \Deriver\Deriver\Evaluator\Term Evaluated expression
     */
    public function unary(string $operator, \Deriver\Deriver\Evaluator\Term $value): \Deriver\Deriver\Evaluator\Term
    {
        if ($operator === 'Expr_BooleanNot') {
            $truth = $this->truth($value);
            return $truth === null ? new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Unary, '!', [$value], ['type' => 'bool']) : \Deriver\Deriver\Evaluator\Term::constant(!$truth);
        }
        if ($operator === 'Expr_UnaryMinus' || $operator === 'Expr_UnaryPlus') {
            return $this->binary('*', $value, \Deriver\Deriver\Evaluator\Term::constant($operator === 'Expr_UnaryMinus' ? -1 : 1));
        }
        if ($operator === 'Expr_BitwiseNot' && $value->kind === \Deriver\Deriver\Evaluator\TermKind::Constant) {
            return is_int($value->literal) || is_string($value->literal) ? \Deriver\Deriver\Evaluator\Term::constant(~$value->literal) : new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'TypeError');
        }
        return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Unary, $operator, [$value]);
    }

    /**
     * Normalizes a PHP array key under the 64-bit target profile.
     * @param \Deriver\Deriver\Evaluator\Term $value Key expression
     * @return \Deriver\Deriver\Evaluator\Term Integer or string key, symbolic key, or TypeError
     */
    public function arrayKey(\Deriver\Deriver\Evaluator\Term $value): \Deriver\Deriver\Evaluator\Term
    {
        if (in_array($value->kind, [\Deriver\Deriver\Evaluator\TermKind::Array, \Deriver\Deriver\Evaluator\TermKind::Object, \Deriver\Deriver\Evaluator\TermKind::Closure, \Deriver\Deriver\Evaluator\TermKind::Enum], true)) {
            return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'TypeError');
        }
        if ($value->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant) {
            return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::ArrayKey, operands: [$value]);
        }
        $key = $value->literal;
        if (is_string($key)) {
            if (preg_match('/^(?:0|-?[1-9][0-9]*)$/D', $key) === 1 && (string) (int) $key === $key) {
                return \Deriver\Deriver\Evaluator\Term::constant((int) $key);
            }
            return $value;
        }
        if ($key === null) {
            return \Deriver\Deriver\Evaluator\Term::constant('');
        }
        if (is_float($key)) {
            return \Deriver\Deriver\Evaluator\Term::constant((new IntegerConversion())->apply($key));
        }
        return \Deriver\Deriver\Evaluator\Term::constant((int) $key);
    }

    /**
     * Applies numeric and bitwise operators after array union and comparison dispatch.
     * @param string $operator Numeric or bitwise operator
     * @param \Deriver\Deriver\Evaluator\Term $left Left operand
     * @param \Deriver\Deriver\Evaluator\Term $right Right operand
     * @return \Deriver\Deriver\Evaluator\Term Target result or throwable
     */
    public function numeric(string $operator, \Deriver\Deriver\Evaluator\Term $left, \Deriver\Deriver\Evaluator\Term $right): \Deriver\Deriver\Evaluator\Term
    {
        if ($operator === '+' && ($left->kind === \Deriver\Deriver\Evaluator\TermKind::Array || $right->kind === \Deriver\Deriver\Evaluator\TermKind::Array)) {
            $other = $left->kind === \Deriver\Deriver\Evaluator\TermKind::Array ? $right : $left;
            if (!in_array($other->kind, [\Deriver\Deriver\Evaluator\TermKind::Constant, \Deriver\Deriver\Evaluator\TermKind::Object, \Deriver\Deriver\Evaluator\TermKind::Closure, \Deriver\Deriver\Evaluator\TermKind::Enum], true)) {
                return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Binary, '+', [$left, $right], ['type' => 'array']);
            }
        }
        if (in_array($left->kind, [\Deriver\Deriver\Evaluator\TermKind::Array, \Deriver\Deriver\Evaluator\TermKind::Object, \Deriver\Deriver\Evaluator\TermKind::Closure, \Deriver\Deriver\Evaluator\TermKind::Enum], true) || in_array($right->kind, [\Deriver\Deriver\Evaluator\TermKind::Array, \Deriver\Deriver\Evaluator\TermKind::Object, \Deriver\Deriver\Evaluator\TermKind::Closure, \Deriver\Deriver\Evaluator\TermKind::Enum], true)) {
            return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'TypeError');
        }
        if ($left->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || $right->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant) {
            return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Binary, $operator, [$left, $right], ['type' => match ($operator) {
                '%', '<<', '>>' => 'int',
                '&', '|', '^' => 'int|string',
                '+' => 'int|float|array',
                default => 'int|float',
            }]);
        }
        return (new Arithmetic())->apply($operator, $left, $right);
    }
}
