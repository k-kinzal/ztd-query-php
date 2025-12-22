<?php

declare(strict_types=1);

namespace SqlFixture\TypeMapper;

use Faker\Generator;
use SqlFixture\Platform\MySql\MySqlTypeMapper as PlatformMySqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;

/**
 * @deprecated Use SqlFixture\Platform\MySql\MySqlTypeMapper instead
 */
final class MySqlTypeMapper implements TypeMapperInterface
{
    private PlatformMySqlTypeMapper $mapper;

    public function __construct()
    {
        $this->mapper = new PlatformMySqlTypeMapper();
    }

    public function generate(Generator $faker, ColumnDefinition $column): mixed
    {
        return $this->mapper->generate($faker, $column);
    }
}
