<?php

declare(strict_types=1);

namespace SqlFixture\TypeMapper;

use SqlFixture\Schema\ColumnDefinition;

/**
 * The finite decimal interval described by precision, scale and signedness.
 *
 * @visibility root
 */
final class DecimalRange
{
    /**
     * Number of fractional decimal digits.
     */
    public readonly int $scale;

    /**
     * Smallest value permitted by the decimal declaration.
     */
    public readonly float $minimum;

    /**
     * Largest value permitted by the decimal declaration.
     */
    public readonly float $maximum;

    /**
     * Includes the fractional part of the highest representable decimal value.
     */
    public function __construct(ColumnDefinition $column, bool $unsigned = false)
    {
        $precision = $column->precision ?? 10;
        $this->scale = $column->scale ?? 0;
        $this->maximum = (float) pow(10, $precision - $this->scale) - pow(10, -$this->scale);
        $this->minimum = $unsigned ? 0.0 : -$this->maximum;
    }
}
