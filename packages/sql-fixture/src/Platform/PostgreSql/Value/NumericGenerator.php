<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Value;

use Faker\Generator;
use LogicException;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Generates numeric values for the dialect's declared column type.
 *
 * @visibility root
 */
final class NumericGenerator
{
    /**
     * Generates a value for a supported member of this type family.
     * @throws LogicException
     */
    public function generate(Generator $faker, ColumnDefinition $column): int|float
    {
        return match (strtoupper($column->type)) {
            'SMALLINT', 'INT2' => $faker->numberBetween(-32768, 32767),
            'INTEGER', 'INT', 'INT4' => $faker->numberBetween(-2147483648, 2147483647),
            'BIGINT', 'INT8' => $faker->numberBetween(PHP_INT_MIN, PHP_INT_MAX),
            'REAL', 'FLOAT4' => $faker->randomFloat(2, -1000.0, 1000.0),
            'DOUBLE PRECISION', 'FLOAT8' => $faker->randomFloat(4, -1000000.0, 1000000.0),
            'DECIMAL', 'NUMERIC', 'DEC' => (new DecimalGenerator())->generateDecimal($faker, $column),
            'MONEY' => $faker->randomFloat(2, 0.0, 99999.99),
            default => throw new LogicException('Unsupported numeric type: ' . $column->type),
        };
    }
}
