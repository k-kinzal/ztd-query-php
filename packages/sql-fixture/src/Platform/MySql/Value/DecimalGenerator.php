<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Value;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Generates values for the declared precision and scale.
 *
 * @visibility root
 */
final class DecimalGenerator
{
    /**
     * Generates decimal.
     */
    public function generateDecimal(Generator $faker, ColumnDefinition $column): float
    {
        $precision = $column->precision ?? 10;
        $scale = $column->scale ?? 0;
        $integerDigits = $precision - $scale;

        $max = (float) pow(10, $integerDigits) - 1;
        $min = $column->unsigned ? 0.0 : -$max;

        return $faker->randomFloat($scale, $min, $max);
    }
}
