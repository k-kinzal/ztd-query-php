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
        $range = new \SqlFixture\TypeMapper\DecimalRange($column, $column->unsigned);
        return $faker->randomFloat($range->scale, $range->minimum, $range->maximum);
    }
}
