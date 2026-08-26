<?php

declare(strict_types=1);

namespace SqlFixture\TypeMapper;

use Faker\Generator;
use SqlFixture\Platform\MySql\MySqlTypeMapper as PlatformMySqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Kept so code written against the old namespace keeps working.
 *
 * Everything it is asked it forwards to the platform reader, which is where
 * the behaviour lives.
 *
 * @deprecated Use SqlFixture\Platform\MySql\MySqlTypeMapper instead
 */
final class MySqlTypeMapper implements TypeMapperInterface
{
    private PlatformMySqlTypeMapper $mapper;

    /**
     * Builds a mapper that delegates to the platform one.
     */
    public function __construct()
    {
        $this->mapper = new PlatformMySqlTypeMapper();
    }

    /**
     * Answers what the column is given in a fixture row.
     *
     * @param Generator $faker Source of every choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int|float|string|bool|null The value, or null when the server fills the column in itself
     */
    public function generate(Generator $faker, ColumnDefinition $column): int|float|string|bool|null
    {
        return $this->mapper->generate($faker, $column);
    }
}
