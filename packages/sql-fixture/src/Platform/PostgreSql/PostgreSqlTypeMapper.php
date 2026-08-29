<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

use Faker\Generator;
use Override;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Decides what a PostgreSQL column is given when a fixture row is built.
 *
 * Two questions are separate here. Whether a column should be given anything
 * at all is a matter of how the table is declared: a SERIAL or generated
 * column must be left alone, and a nullable one is occasionally left at its
 * default so that fixtures exercise that path too. What the value looks like
 * once one is wanted is a matter of the column's type, which is answered
 * elsewhere.
 */
final class PostgreSqlTypeMapper implements TypeMapperInterface
{
    /**
     * @param PostgreSqlColumnSample $sample Picks a value of the kind the column's type calls for
     */
    public function __construct(
        private readonly PostgreSqlColumnSample $sample = new PostgreSqlColumnSample(),
    ) {
    }

    /**
     * Answers what the column is given in a fixture row.
     *
     * @param Generator $faker Source of every choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int|float|string|bool|null The value, or null when the server fills the column in itself
     *
     */
    #[Override]
    public function generate(Generator $faker, ColumnDefinition $column): int|float|string|bool|null
    {
        if ($column->autoIncrement || $column->generated) {
            return null;
        }

        $value = $this->sample->of($faker, $column);
        if ($column->nullable && $faker->boolean(10)) {
            return $column->default;
        }

        return $value;
    }
}
