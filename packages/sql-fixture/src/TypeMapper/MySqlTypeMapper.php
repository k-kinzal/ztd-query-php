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

    /**
     * Initializes the collaborators and declared state for this object.
     */
    public function __construct()
    {
        $this->mapper = new PlatformMySqlTypeMapper();
    }

    /**
     * Generates fixture data according to the supplied schema or plan.
     */
    public function generate(Generator $faker, ColumnDefinition $column): mixed
    {
        return $this->mapper->generate($faker, $column);
    }
}
