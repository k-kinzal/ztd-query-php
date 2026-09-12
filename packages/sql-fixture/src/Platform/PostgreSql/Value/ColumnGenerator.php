<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Value;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Generates values from the dialect column type.
 *
 * @visibility root
 */
final class ColumnGenerator
{
    /**
     * Generates value.
     */
    public function generateValue(Generator $faker, ColumnDefinition $column): int|float|bool|string
    {
        $type = strtoupper($column->type);

        if (in_array($type, ['SMALLINT', 'INT2', 'INTEGER', 'INT', 'INT4', 'BIGINT', 'INT8', 'REAL', 'FLOAT4', 'DOUBLE PRECISION', 'FLOAT8', 'DECIMAL', 'NUMERIC', 'DEC', 'MONEY'], true)) {
            return (new NumericGenerator())->generate($faker, $column);
        }

        if (in_array($type, ['DATE', 'TIME', 'TIME WITHOUT TIME ZONE', 'TIME WITH TIME ZONE', 'TIMETZ', 'TIMESTAMP', 'TIMESTAMP WITHOUT TIME ZONE', 'TIMESTAMP WITH TIME ZONE', 'TIMESTAMPTZ', 'INTERVAL'], true)) {
            return (new TemporalGenerator())->generate($faker, $column);
        }

        return match ($type) {

            'BOOLEAN', 'BOOL' => $faker->boolean(),

            'CHAR', 'CHARACTER' => (new StringGenerator())->generateChar($faker, $column),
            'VARCHAR', 'CHARACTER VARYING' => (new StringGenerator())->generateVarchar($faker, $column),
            'TEXT' => (new \SqlFixture\TypeMapper\ParagraphGenerator())->generate($faker, 2),

            'BYTEA' => (new StructuredGenerator())->generateBytea($faker),

            'JSON' => (new StructuredGenerator())->generateJson($faker),
            'JSONB' => (new StructuredGenerator())->generateJson($faker),

            'UUID' => $faker->uuid(),

            'INET' => $faker->ipv4(),
            'CIDR' => $faker->ipv4() . '/24',
            'MACADDR' => $faker->macAddress(),

            'INTEGER_ARRAY', 'INT_ARRAY' => (new StructuredGenerator())->generateIntArray($faker),
            'TEXT_ARRAY' => (new StructuredGenerator())->generateTextArray($faker),

            'XML' => '<root>' . $faker->text(50) . '</root>',

            default => $faker->text(50),
        };
    }
}
