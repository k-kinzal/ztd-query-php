<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use Faker\Generator;
use Override;
use Random\RandomException;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Decides what a SQLite column is given when a fixture row is built.
 *
 * Two questions are separate here. Whether a column should be given anything
 * at all is a matter of how the table is declared: an INTEGER PRIMARY KEY or a
 * generated column must be left alone, and a nullable one is occasionally left
 * at its default so that fixtures exercise that path too. What the value looks
 * like once one is wanted follows from the column's affinity, which is
 * answered elsewhere.
 */
final class SqliteTypeMapper implements TypeMapperInterface
{
    /**
     * @param SqliteColumnSample $sample Picks a value of the kind the column's affinity calls for
     */
    public function __construct(private readonly SqliteColumnSample $sample = new SqliteColumnSample())
    {
    }

    /**
     * Answers what the column is given in a fixture row.
     *
     * @param Generator $faker Source of every choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int|float|string|bool|null The value, or null when the server fills the column in itself
     *
     * @throws RandomException When a blob column is asked for and the system has no source of randomness
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
