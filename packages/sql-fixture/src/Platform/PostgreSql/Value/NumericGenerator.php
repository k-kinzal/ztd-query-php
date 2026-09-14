<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Value;

use Faker\Generator;
use LogicException;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\IntegerRange;
use SqlFixture\TypeMapper\IntegerWidth;

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
        $type = strtoupper($column->type);
        $width = match ($type) {
            'SMALLINT', 'INT2' => IntegerWidth::Bits16,
            'INTEGER', 'INT', 'INT4' => IntegerWidth::Bits32,
            'BIGINT', 'INT8' => IntegerWidth::Bits64,
            default => null,
        };
        if ($width !== null) {
            $range = new IntegerRange($width);
            return $faker->numberBetween($range->minimum, $range->maximum);
        }
        return match ($type) {
            'REAL', 'FLOAT4' => $faker->randomFloat(2, -1000.0, 1000.0),
            'DOUBLE PRECISION', 'FLOAT8' => $faker->randomFloat(4, -1000000.0, 1000000.0),
            'DECIMAL', 'NUMERIC', 'DEC' => (new DecimalGenerator())->generateDecimal($faker, $column),
            'MONEY' => $faker->randomFloat(2, 0.0, 99999.99),
            default => throw new LogicException('Unsupported numeric type: ' . $column->type),
        };
    }
}
