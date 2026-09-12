<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Value;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;

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

        if ($column->unsigned) {
            return $faker->numberBetween(0, 255);
        }
        return $faker->numberBetween(-128, 127);
    }

    /**
     * Generates small int.
     */
    public function generateSmallInt(Generator $faker, ColumnDefinition $column): int
    {
        if ($column->unsigned) {
            return $faker->numberBetween(0, 65535);
        }
        return $faker->numberBetween(-32768, 32767);
    }

    /**
     * Generates medium int.
     */
    public function generateMediumInt(Generator $faker, ColumnDefinition $column): int
    {
        if ($column->unsigned) {
            return $faker->numberBetween(0, 16777215);
        }
        return $faker->numberBetween(-8388608, 8388607);
    }

    /**
     * Generates int.
     */
    public function generateInt(Generator $faker, ColumnDefinition $column): int
    {
        if ($column->unsigned) {
            return $faker->numberBetween(0, 4294967295);
        }
        return $faker->numberBetween(-2147483648, 2147483647);
    }

    /**
     * Generates big int.
     */
    public function generateBigInt(Generator $faker, ColumnDefinition $column): int
    {
        if ($column->unsigned) {
            return $faker->numberBetween(0, PHP_INT_MAX);
        }
        return $faker->numberBetween(PHP_INT_MIN, PHP_INT_MAX);
    }

    /**
     * Generates bit.
     */
    public function generateBit(Generator $faker, ColumnDefinition $column): int
    {
        $length = $column->length ?? 1;
        $max = (int) pow(2, $length) - 1;
        return $faker->numberBetween(0, $max);
    }
}
