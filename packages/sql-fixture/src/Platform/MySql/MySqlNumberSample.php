<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Picks a number a MySQL numeric column will accept.
 *
 * Every MySQL integer type is a fixed width, and declaring it unsigned moves
 * the whole range rather than widening it, so a value that fits INT signed may
 * not fit TINYINT and a negative value fits neither once the column is
 * unsigned. DECIMAL is bounded by its own precision and scale instead, and BIT
 * by how many bits were declared. Getting any of those wrong produces a
 * fixture the server refuses, so each bound is stated once here.
 */
final class MySqlNumberSample
{
    /**
     * Picks a value TINYINT will accept, or a boolean when the column is TINYINT(1).
     *
     * MySQL has no boolean type: BOOL is TINYINT(1), and a driver reading such
     * a column expects true or false rather than 0 or 1.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int|bool A value the column accepts
     */
    public function tinyInt(Generator $faker, ColumnDefinition $column): int|bool
    {
        if ($column->length === 1) {
            return $faker->boolean();
        }

        return $column->unsigned ? $faker->numberBetween(0, 255) : $faker->numberBetween(-128, 127);
    }

    /**
     * Picks a value SMALLINT will accept.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int A value the column accepts
     */
    public function smallInt(Generator $faker, ColumnDefinition $column): int
    {
        return $column->unsigned ? $faker->numberBetween(0, 65535) : $faker->numberBetween(-32768, 32767);
    }

    /**
     * Picks a value MEDIUMINT will accept.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int A value the column accepts
     */
    public function mediumInt(Generator $faker, ColumnDefinition $column): int
    {
        return $column->unsigned
            ? $faker->numberBetween(0, 16777215)
            : $faker->numberBetween(-8388608, 8388607);
    }

    /**
     * Picks a value INT will accept.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int A value the column accepts
     */
    public function int(Generator $faker, ColumnDefinition $column): int
    {
        return $column->unsigned
            ? $faker->numberBetween(0, 4294967295)
            : $faker->numberBetween(-2147483648, 2147483647);
    }

    /**
     * Picks a value BIGINT will accept.
     *
     * BIGINT UNSIGNED reaches past what PHP can hold as an integer, so the
     * range is capped at PHP_INT_MAX rather than the column's own maximum.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int A value the column accepts
     */
    public function bigInt(Generator $faker, ColumnDefinition $column): int
    {
        return $column->unsigned
            ? $faker->numberBetween(0, PHP_INT_MAX)
            : $faker->numberBetween(PHP_INT_MIN, PHP_INT_MAX);
    }

    /**
     * Picks a value DECIMAL will accept.
     *
     * Precision counts every digit and scale counts the ones after the point,
     * so the digits before it are what is left, and that is what bounds the
     * value. An undeclared DECIMAL is DECIMAL(10,0).
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return float A value the column accepts
     */
    public function decimal(Generator $faker, ColumnDefinition $column): float
    {
        $precision = $column->precision ?? 10;
        $scale = $column->scale ?? 0;
        $max = (float) pow(10, $precision - $scale) - 1;

        return $faker->randomFloat($scale, $column->unsigned ? 0.0 : -$max, $max);
    }

    /**
     * Picks a value BIT will accept.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int A value that fits in the declared number of bits
     */
    public function bit(Generator $faker, ColumnDefinition $column): int
    {
        return $faker->numberBetween(0, (int) pow(2, $column->length ?? 1) - 1);
    }
}
