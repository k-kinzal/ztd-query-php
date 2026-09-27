<?php

declare(strict_types=1);

namespace Deriver\Value;

/**
 * Implements PHP 8.3 double-to-integer wrapping without invoking host range diagnostics.
 * @visibility root
 */
final class IntegerConversion
{
    /**
     * Converts a numeric operand to a signed 64-bit integer using the target wrap rule.
     * @param int|float $value Numeric operand
     * @return int Truncated and wrapped target integer
     */
    public function apply(int|float $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_finite($value)) {
            return 0;
        }
        $wrapped = fmod($value < 0 ? ceil($value) : floor($value), 18446744073709551616.0);
        if ($wrapped >= 9223372036854775808.0) {
            $wrapped -= 18446744073709551616.0;
        } elseif ($wrapped < -9223372036854775808.0) {
            $wrapped += 18446744073709551616.0;
        }
        return (int) $wrapped;
    }

    /**
     * Detects implicit precision loss, including nonfinite and out-of-range operands.
     * @param int|float $value Numeric operand
     * @return bool Whether the target emits an implicit conversion diagnostic
     */
    public function warning(int|float $value): bool
    {
        return is_float($value) && (float) $this->apply($value) !== $value;
    }
}
