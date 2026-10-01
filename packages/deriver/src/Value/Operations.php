<?php

declare(strict_types=1);

namespace Deriver\Value;

/**
 * Pure PHP 8.3 scalar and array operations, independent of application execution.
 * @visibility root
 */
final class Operations
{
    /**
     * Converts a known or symbolic value to a PHP boolean.
     * @param Term $value Input
     * @return bool|null Known truth value, or null when symbolic
     */
    public function truth(Term $value): ?bool
    {
        if ($value->kind === 'constant') {
            return (bool) $value->literal;
        }
        if ($value->kind === 'array') {
            return $value->operands !== [] ? true : (($value->attributes['open'] ?? false) === false ? false : null);
        }
        if (in_array($value->kind, ['object', 'closure', 'enum'], true)) {
            return true;
        }
        if ($value->kind === 'uninitialized') {
            return false;
        }
        return null;
    }

    /**
     * Applies a type conversion while retaining symbolic input identity.
     * @param string $type Target scalar type
     * @param Term $value Input
     * @return Term Cast expression or concrete result
     */
    public function cast(string $type, Term $value): Term
    {
        $type = strtolower($type);
        if ($value->kind === 'cast' && $value->literal === $type) {
            return $value;
        }
        if ($type === 'bool') {
            $truth = $this->truth($value);
            return $truth === null ? new Term('cast', 'bool', [$value], ['type' => 'bool']) : Term::constant($truth, $value->isSecret());
        }
        if ($value->kind !== 'constant') {
            if ($type === 'string' && in_array($value->kind, ['array', 'object'], true)) {
                return Term::opaque('UNSUPPORTED_LANGUAGE_FEATURE', 'string', [$value]);
            }
            return new Term('cast', $type, [$value], ['type' => $type]);
        }
        $native = $value->literal;
        if ($type === 'string') {
            return is_float($native) ? Term::opaque('FLOAT_STRING_CONFIGURATION', 'string', [$value]) : Term::constant((string) $native, $value->isSecret());
        }
        if ($type === 'int') {
            if (is_float($native)) {
                return Term::constant((new IntegerConversion())->apply($native), $value->isSecret());
            }
            return Term::constant((int) $native, $value->isSecret());
        }
        if ($type === 'float' || $type === 'double') {
            return Term::constant((float) $native, $value->isSecret());
        }
        if ($type === 'array') {
            return new Term('array', operands: $native === null ? [] : [Term::constant($native, $value->secret)], attributes: ['open' => false], secret: $value->isSecret());
        }
        return Term::opaque('UNSUPPORTED_LANGUAGE_FEATURE', $type, [$value]);
    }

    /**
     * Applies a binary operation without invoking user code.
     * @param string $operator PHP operator
     * @param Term $left Left value
     * @param Term $right Right value
     * @return Term Constant, symbolic operation, or explicit throwable
     */
    public function binary(string $operator, Term $left, Term $right): Term
    {
        if ($operator === '.') {
            $a = $this->cast('string', $left);
            $b = $this->cast('string', $right);
            if ($a->kind === 'constant' && $b->kind === 'constant' && is_string($a->literal) && is_string($b->literal)) {
                return Term::constant($a->literal . $b->literal, $a->isSecret() || $b->isSecret());
            }
            return new Term('concat', operands: [$a, $b], attributes: ['type' => 'string']);
        }
        if ($operator === 'xor') {
            $a = $this->truth($left);
            $b = $this->truth($right);
            return $a !== null && $b !== null ? Term::constant($a !== $b, $left->isSecret() || $right->isSecret()) : new Term('binary', 'xor', [$left, $right], ['type' => 'bool']);
        }
        if ($operator === '+' && $left->kind === 'array' && $right->kind === 'array') {
            return new Term('array', operands: $left->operands + $right->operands, attributes: ['open' => ($left->attributes['open'] ?? false) === true || ($right->attributes['open'] ?? false) === true], secret: $left->isSecret() || $right->isSecret());
        }
        if (in_array($operator, ['===', '!==', '==', '!=', '<', '<=', '>', '>=', '<=>'], true)) {
            return (new Comparison())->apply($operator, $left, $right);
        }
        return $this->numeric($operator, $left, $right);
    }

    /**
     * Applies unary operators with explicit errors.
     * @param string $operator Parser-independent operator name
     * @param Term $value Operand
     * @return Term Evaluated expression
     */
    public function unary(string $operator, Term $value): Term
    {
        if ($operator === 'Expr_BooleanNot') {
            $truth = $this->truth($value);
            return $truth === null ? new Term('unary', '!', [$value], ['type' => 'bool']) : Term::constant(!$truth, $value->isSecret());
        }
        if ($operator === 'Expr_UnaryMinus' || $operator === 'Expr_UnaryPlus') {
            return $this->binary($operator === 'Expr_UnaryMinus' ? '-' : '+', Term::constant(0), $value);
        }
        if ($operator === 'Expr_BitwiseNot' && $value->kind === 'constant') {
            return is_int($value->literal) || is_string($value->literal) ? Term::constant(~$value->literal, $value->isSecret()) : new Term('throwable', 'TypeError');
        }
        return new Term('unary', $operator, [$value]);
    }

    /**
     * Normalizes a PHP array key under the 64-bit target profile.
     * @param Term $value Key expression
     * @return Term Integer or string key, symbolic key, or TypeError
     */
    public function arrayKey(Term $value): Term
    {
        if (in_array($value->kind, ['array', 'object', 'closure', 'enum'], true)) {
            return new Term('throwable', 'TypeError');
        }
        if ($value->kind !== 'constant') {
            return new Term('array-key', operands: [$value]);
        }
        $key = $value->literal;
        if (is_string($key)) {
            if (preg_match('/^(?:0|-?[1-9][0-9]*)$/D', $key) === 1 && (string) (int) $key === $key) {
                return Term::constant((int) $key, $value->isSecret());
            }
            return $value;
        }
        if ($key === null) {
            return Term::constant('', $value->isSecret());
        }
        if (is_float($key)) {
            return Term::constant((new IntegerConversion())->apply($key), $value->isSecret());
        }
        return Term::constant((int) $key, $value->isSecret());
    }

    /**
     * Applies numeric and bitwise operators after array union and comparison dispatch.
     * @param string $operator Numeric or bitwise operator
     * @param Term $left Left operand
     * @param Term $right Right operand
     * @return Term Target result or throwable
     */
    public function numeric(string $operator, Term $left, Term $right): Term
    {
        if ($operator === '+' && ($left->kind === 'array' || $right->kind === 'array')) {
            $other = $left->kind === 'array' ? $right : $left;
            if (!in_array($other->kind, ['constant', 'object', 'closure', 'enum'], true)) {
                return new Term('binary', '+', [$left, $right], ['type' => 'array']);
            }
        }
        if (in_array($left->kind, ['array', 'object', 'closure', 'enum'], true) || in_array($right->kind, ['array', 'object', 'closure', 'enum'], true)) {
            return new Term('throwable', 'TypeError');
        }
        if ($left->kind !== 'constant' || $right->kind !== 'constant') {
            return new Term('binary', $operator, [$left, $right], ['type' => match ($operator) {
                '%', '<<', '>>' => 'int',
                '&', '|', '^' => 'int|string',
                '+' => 'int|float|array',
                default => 'int|float',
            }]);
        }
        return (new Arithmetic())->apply($operator, $left, $right);
    }
}
