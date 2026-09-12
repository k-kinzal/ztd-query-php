<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Generates fixture values from MySQL column declarations.
 */
final class MySqlTypeMapper implements TypeMapperInterface
{
    /**
     * Generates fixture data according to the supplied schema or plan.
     */
    public function generate(Generator $faker, ColumnDefinition $column): mixed
    {
        if ($column->autoIncrement || $column->generated) {
            return null;
        }

        $value = (new Value\ColumnGenerator())->generateValue($faker, $column);

        if ($column->nullable && $value !== null) {
            $shouldUseDefault = $faker->boolean(10);
            if ($shouldUseDefault) {
                return $column->default;
            }
        }

        return $value;
    }










































}
