<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Value;

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
    public function generate(Generator $faker, ColumnDefinition $column): int|float|bool
    {
        return match (strtoupper($column->type)) {
            'TINYINT' => (new IntegerGenerator())->generateTinyInt($faker, $column),
            'SMALLINT' => (new IntegerGenerator())->generateSmallInt($faker, $column),
            'MEDIUMINT' => (new IntegerGenerator())->generateMediumInt($faker, $column),
            'INT', 'INTEGER' => (new IntegerGenerator())->generateInt($faker, $column),
            'BIGINT' => (new IntegerGenerator())->generateBigInt($faker, $column),
            'FLOAT' => $faker->randomFloat(2, -1000.0, 1000.0),
            'DOUBLE', 'REAL' => $faker->randomFloat(4, -1000000.0, 1000000.0),
            'DECIMAL', 'NUMERIC', 'DEC', 'FIXED' => (new DecimalGenerator())->generateDecimal($faker, $column),
            'BIT' => (new IntegerGenerator())->generateBit($faker, $column),
            default => throw new LogicException('Unsupported numeric type: ' . $column->type),
        };
    }
}
