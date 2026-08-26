<?php

declare(strict_types=1);

namespace SqlFixture\TypeMapper;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Decides what one column is given when a fixture row is built.
 *
 * Each server has its own types and its own idea of what they will accept, so
 * each brings its own mapper.
 *
 * @phpstan-type FixtureValue int|float|string|bool|null
 * @phpstan-type FixtureOverride FixtureValue|array<array-key, FixtureValue>
 * @phpstan-type FixtureRow array<array-key, FixtureOverride>
 */
interface TypeMapperInterface
{
    /**
     * Answers what the column is given in a fixture row.
     *
     * A fixture value is something a driver can bind, so it is a scalar or
     * null. A column the server fills in itself is given null, which is how a
     * row says "leave this one alone".
     *
     * @param Generator $faker Source of every choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return FixtureValue The value the column is given
     */
    public function generate(Generator $faker, ColumnDefinition $column): int|float|string|bool|null;
}
