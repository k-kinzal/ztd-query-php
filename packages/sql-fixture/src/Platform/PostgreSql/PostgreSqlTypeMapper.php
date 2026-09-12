<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Type mapper for PostgreSQL column types.
 *
 * Maps PostgreSQL-specific types to appropriate Faker-generated values:
 * - Standard SQL types (INTEGER, TEXT, BOOLEAN, etc.)
 * - PostgreSQL-specific types (UUID, JSONB, BYTEA, INET, CIDR, MACADDR, etc.)
 * - SERIAL types (mapped to INTEGER with autoIncrement)
 * - Array types
 * - Timestamp with/without time zone
 */
final class PostgreSqlTypeMapper implements TypeMapperInterface
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

        if ($column->nullable) {
            $shouldUseDefault = $faker->boolean(10);
            if ($shouldUseDefault) {
                return $column->default;
            }
        }

        return $value;
    }

}
