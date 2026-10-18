<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Scalar;

/**
 * Implements PHP 8.3 increment and decrement independently of host string increment changes.
 * @visibility root
 */
final class Increment
{
    /**
     * Computes the new scalar while preserving the original type on no-effect operations.
     * @param \Deriver\Deriver\Evaluator\Term $before Value before the update
     * @param int $delta Positive for increment, negative for decrement
     * @return \Deriver\Deriver\Evaluator\Term Updated value or a target TypeError
     */
    public function apply(\Deriver\Deriver\Evaluator\Term $before, int $delta): \Deriver\Deriver\Evaluator\Term
    {
        if ($before->kind === \Deriver\Deriver\Evaluator\TermKind::Uninitialized) {
            $before = \Deriver\Deriver\Evaluator\Term::constant(null);
        }
        if ($before->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant) {
            if (in_array($before->kind, [\Deriver\Deriver\Evaluator\TermKind::Array, \Deriver\Deriver\Evaluator\TermKind::Object, \Deriver\Deriver\Evaluator\TermKind::Closure, \Deriver\Deriver\Evaluator\TermKind::Enum], true)) {
                return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'TypeError');
            }
            $type = $before->attributes['type'] ?? 'mixed';
            return in_array($type, ['int', 'float', 'int|float'], true) ? (new Operations())->binary('+', $before, \Deriver\Deriver\Evaluator\Term::constant($delta)) : new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Increment, $delta, [$before], ['type' => 'mixed']);
        }
        $value = $before->literal;
        if (is_bool($value) || ($value === null && $delta < 0)) {
            return $before;
        }
        if ($value === null) {
            return \Deriver\Deriver\Evaluator\Term::constant(1);
        }
        if (is_string($value) && !is_numeric($value)) {
            if ($delta < 0) {
                return $value === '' ? \Deriver\Deriver\Evaluator\Term::constant(-1) : $before;
            }
            return \Deriver\Deriver\Evaluator\Term::constant($this->string($value));
        }
        return (new Operations())->binary('+', $before, \Deriver\Deriver\Evaluator\Term::constant($delta));
    }

    /**
     * Identifies PHP 8.3 warnings without turning their normal result into an exception.
     * @param \Deriver\Deriver\Evaluator\Term $before Original scalar
     * @param int $delta Increment direction
     * @return bool Whether the target emits a warning for this scalar operation
     */
    public function warning(\Deriver\Deriver\Evaluator\Term $before, int $delta): bool
    {
        if ($before->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant) {
            return false;
        }
        $value = $before->literal;
        return is_bool($value) || ($delta < 0 && ($value === null || is_string($value) && !is_numeric($value))) || ($delta > 0 && is_string($value) && !is_numeric($value) && preg_match('/^[a-zA-Z0-9]+$/D', $value) !== 1);
    }

    /**
     * Performs ASCII alphanumeric carry without invoking the host PHP increment operator.
     * @param string $value Non-numeric string
     * @return string PHP 8.3 incremented string
     */
    public function string(string $value): string
    {
        if ($value === '') {
            return '1';
        }
        $prefix = '1';
        for ($index = strlen($value) - 1; $index >= 0; $index--) {
            $byte = ord($value[$index]);
            if ($byte === 57 || $byte === 90 || $byte === 122) {
                $value[$index] = match ($byte) {
                    57 => '0', 90 => 'A', 122 => 'a'
                };
                $prefix = $byte === 57 ? '1' : $value[$index];
                continue;
            }
            if (($byte >= 48 && $byte < 57) || ($byte >= 65 && $byte < 90) || ($byte >= 97 && $byte < 122)) {
                $value[$index] = chr($byte + 1);
            }
            return $value;
        }
        return $prefix . $value;
    }
}
