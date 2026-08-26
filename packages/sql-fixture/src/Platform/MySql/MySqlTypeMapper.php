<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use Faker\Generator;
use LogicException;
use Override;
use Random\RandomException;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Decides what a MySQL column is given when a fixture row is built.
 *
 * Two questions are separate here. Whether a column should be given anything
 * at all is a matter of how the table is declared: a column the server fills
 * in must be left alone, and a nullable one is occasionally left at its
 * default so that fixtures exercise that path too. What the value looks like
 * once one is wanted is a matter of the column's type, which is answered
 * elsewhere.
 */
final class MySqlTypeMapper implements TypeMapperInterface
{
    /**
     * @param MySqlColumnSample $sample Picks a value of the kind the column's type calls for
     */
    public function __construct(private readonly MySqlColumnSample $sample = new MySqlColumnSample())
    {
    }

    /**
     * Answers what the column is given in a fixture row.
     *
     * A column the server fills in — AUTO_INCREMENT or GENERATED — is given
     * nothing, so the server's own value stands. A nullable column is given
     * its declared default one time in ten, which is how a fixture reaches the
     * behaviour of a column that was left unset.
     *
     * @param Generator $faker Source of every choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int|float|string|bool|null The value, or null when the server fills the column in itself
     *
     * @throws LogicException When a chosen SET member is not a string
     * @throws RandomException When a binary column is asked for and the system has no source of randomness
     */
    #[Override]
    public function generate(Generator $faker, ColumnDefinition $column): int|float|string|bool|null
    {
        if ($column->autoIncrement || $column->generated) {
            return null;
        }

        $value = $this->sample->of($faker, $column);
        if ($column->nullable && $value !== null && $faker->boolean(10)) {
            return $column->default;
        }

        return $value;
    }
}
