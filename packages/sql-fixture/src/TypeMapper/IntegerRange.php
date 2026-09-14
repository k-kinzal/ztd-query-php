<?php

declare(strict_types=1);

namespace SqlFixture\TypeMapper;

/**
 * SQL integer bounds restricted to values representable by PHP integers.
 *
 * @visibility root
 */
final class IntegerRange
{
    /**
     * Smallest value accepted by the selected width and signedness.
     */
    public readonly int $minimum;

    /**
     * Largest SQL value that can also be represented as a PHP integer.
     */
    public readonly int $maximum;

    /**
     * Resolves the value domain before a random value is drawn from it.
     */
    public function __construct(IntegerWidth $width, bool $unsigned = false)
    {
        [$minimum, $maximum, $unsignedMaximum] = match ($width) {
            IntegerWidth::Bits8 => [-128, 127, 255],
            IntegerWidth::Bits16 => [-32768, 32767, 65535],
            IntegerWidth::Bits24 => [-8388608, 8388607, 16777215],
            IntegerWidth::Bits32 => [-2147483648, 2147483647, 4294967295],
            IntegerWidth::Bits64 => [PHP_INT_MIN, PHP_INT_MAX, PHP_INT_MAX],
        };
        $this->minimum = $unsigned ? 0 : $minimum;
        $this->maximum = $unsigned ? $unsignedMaximum : $maximum;
    }
}
