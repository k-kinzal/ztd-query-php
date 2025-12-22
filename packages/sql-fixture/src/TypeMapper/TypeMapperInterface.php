<?php

declare(strict_types=1);

namespace SqlFixture\TypeMapper;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;

interface TypeMapperInterface
{
    /**
     * Generate a Faker value based on column definition.
     */
    public function generate(Generator $faker, ColumnDefinition $column): mixed;
}
