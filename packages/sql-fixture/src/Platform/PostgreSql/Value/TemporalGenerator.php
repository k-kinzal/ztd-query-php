<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Value;

use Faker\Generator;
use LogicException;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Generates temporal values for the dialect's declared column type.
 *
 * @visibility root
 */
final class TemporalGenerator
{
    /**
     * Generates a value for a supported member of this type family.
     * @throws LogicException
     */
    public function generate(Generator $faker, ColumnDefinition $column): string
    {
        return match (strtoupper($column->type)) {
            'DATE' => $faker->date('Y-m-d'),
            'TIME', 'TIME WITHOUT TIME ZONE' => $faker->time('H:i:s'),
            'TIME WITH TIME ZONE', 'TIMETZ' => $faker->time('H:i:sP'),
            'TIMESTAMP', 'TIMESTAMP WITHOUT TIME ZONE' => $faker->dateTime()->format('Y-m-d H:i:s'),
            'TIMESTAMP WITH TIME ZONE', 'TIMESTAMPTZ' => $faker->dateTime()->format('Y-m-d H:i:sP'),
            'INTERVAL' => (new StructuredGenerator())->generateInterval($faker),
            default => throw new LogicException('Unsupported temporal type: ' . $column->type),
        };
    }
}
