<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Value;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\IntegerRange;
use SqlFixture\TypeMapper\IntegerWidth;

/**
 * Generates MySQL integers within the declared signed or unsigned range.
 *
 * @visibility root
 */
final class IntegerGenerator
{
    /**
     * Generates tiny int.
     */
    public function generateTinyInt(Generator $faker, ColumnDefinition $column): int|bool
    {
        if ($column->length === 1) {
            return $faker->boolean();
        }

        $range = new IntegerRange(IntegerWidth::Bits8, $column->unsigned);
        return $faker->numberBetween($range->minimum, $range->maximum);
    }

    /**
     * Generates small int.
     */
    public function generateSmallInt(Generator $faker, ColumnDefinition $column): int
    {
        $range = new IntegerRange(IntegerWidth::Bits16, $column->unsigned);
        return $faker->numberBetween($range->minimum, $range->maximum);
    }

    /**
     * Generates medium int.
     */
    public function generateMediumInt(Generator $faker, ColumnDefinition $column): int
    {
        $range = new IntegerRange(IntegerWidth::Bits24, $column->unsigned);
        return $faker->numberBetween($range->minimum, $range->maximum);
    }

    /**
     * Generates int.
     */
    public function generateInt(Generator $faker, ColumnDefinition $column): int
    {
        $range = new IntegerRange(IntegerWidth::Bits32, $column->unsigned);
        return $faker->numberBetween($range->minimum, $range->maximum);
    }

    /**
     * Generates big int.
     */
    public function generateBigInt(Generator $faker, ColumnDefinition $column): int
    {
        $range = new IntegerRange(IntegerWidth::Bits64, $column->unsigned);
        return $faker->numberBetween($range->minimum, $range->maximum);
    }

    /**
     * Generates bit.
     */
    public function generateBit(Generator $faker, ColumnDefinition $column): int
    {
        $length = $column->length ?? 1;
        $max = $length >= PHP_INT_SIZE * 8 - 1 ? PHP_INT_MAX : (1 << $length) - 1;
        return $faker->numberBetween(0, $max);
    }
}
