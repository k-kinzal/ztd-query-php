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
 */
interface TypeMapperInterface
{
    /**
     * Generate a Faker value based on column definition.
     */
    public function generate(Generator $faker, ColumnDefinition $column): mixed;
}
