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
    public function generate(Generator $faker, ColumnDefinition $column): mixed
    {
        if ($column->autoIncrement || $column->generated) {
            return null;
        }

        $value = $this->generateValue($faker, $column);

        if ($column->nullable && $value !== null) {
            $shouldUseDefault = $faker->boolean(10);
            if ($shouldUseDefault) {
                return $column->default;
            }
        }

        return $value;
    }

    private function generateValue(Generator $faker, ColumnDefinition $column): mixed
    {
        $type = strtoupper($column->type);

        return match ($type) {
            'SMALLINT', 'INT2' => $faker->numberBetween(-32768, 32767),
            'INTEGER', 'INT', 'INT4' => $faker->numberBetween(-2147483648, 2147483647),
            'BIGINT', 'INT8' => $faker->numberBetween(PHP_INT_MIN, PHP_INT_MAX),

            'REAL', 'FLOAT4' => $faker->randomFloat(2, -1000.0, 1000.0),
            'DOUBLE PRECISION', 'FLOAT8' => $faker->randomFloat(4, -1000000.0, 1000000.0),

            'DECIMAL', 'NUMERIC', 'DEC' => $this->generateDecimal($faker, $column),
            'MONEY' => $faker->randomFloat(2, 0.0, 99999.99),

            'BOOLEAN', 'BOOL' => $faker->boolean(),

            'CHAR', 'CHARACTER' => $this->generateChar($faker, $column),
            'VARCHAR', 'CHARACTER VARYING' => $this->generateVarchar($faker, $column),
            'TEXT' => $faker->paragraphs(2, true),

            'BYTEA' => $this->generateBytea($faker),

            'DATE' => $faker->date('Y-m-d'),
            'TIME', 'TIME WITHOUT TIME ZONE' => $faker->time('H:i:s'),
            'TIME WITH TIME ZONE', 'TIMETZ' => $faker->time('H:i:sP'),
            'TIMESTAMP', 'TIMESTAMP WITHOUT TIME ZONE' => $faker->dateTime()->format('Y-m-d H:i:s'),
            'TIMESTAMP WITH TIME ZONE', 'TIMESTAMPTZ' => $faker->dateTime()->format('Y-m-d H:i:sP'),
            'INTERVAL' => $this->generateInterval($faker),

            'JSON' => $this->generateJson($faker),
            'JSONB' => $this->generateJson($faker),

            'UUID' => $faker->uuid(),

            'INET' => $faker->ipv4(),
            'CIDR' => $faker->ipv4() . '/24',
            'MACADDR' => $faker->macAddress(),

            'INTEGER_ARRAY', 'INT_ARRAY' => $this->generateIntArray($faker),
            'TEXT_ARRAY' => $this->generateTextArray($faker),

            'XML' => '<root>' . $faker->text(50) . '</root>',

            default => $faker->text(50),
        };
    }

    private function generateDecimal(Generator $faker, ColumnDefinition $column): float
    {
        $precision = $column->precision ?? 10;
        $scale = $column->scale ?? 0;
        $integerDigits = $precision - $scale;

        $max = (float) pow(10, $integerDigits) - 1;
        $min = -$max;

        return $faker->randomFloat($scale, $min, $max);
    }

    private function generateChar(Generator $faker, ColumnDefinition $column): string
    {
        $length = $column->length ?? 1;
        $pattern = str_repeat('?', $length);
        $result = $faker->lexify($pattern);
        return substr($result, 0, $length);
    }

    private function generateVarchar(Generator $faker, ColumnDefinition $column): string
    {
        $maxLength = $column->length ?? 255;
        $text = $faker->text(min($maxLength, 200));
        return substr($text, 0, $maxLength);
    }

    private function generateBytea(Generator $faker): string
    {
        $length = max(1, $faker->numberBetween(1, 100));
        return '\\x' . bin2hex(random_bytes($length));
    }

    private function generateInterval(Generator $faker): string
    {
        $units = ['days', 'hours', 'minutes', 'seconds', 'months', 'years'];
        /** @var string $unit */
        $unit = $faker->randomElement($units);
        $value = $faker->numberBetween(1, 30);

        return "{$value} {$unit}";
    }

    private function generateJson(Generator $faker): string
    {
        $json = json_encode([
            'key' => $faker->text(20),
            'value' => $faker->numberBetween(1, 100),
        ]);

        return $json !== false ? $json : '{}';
    }

    private function generateIntArray(Generator $faker): string
    {
        $count = $faker->numberBetween(1, 5);
        $values = [];
        for ($i = 0; $i < $count; $i++) {
            $values[] = (string) $faker->numberBetween(1, 1000);
        }

        return '{' . implode(',', $values) . '}';
    }

    private function generateTextArray(Generator $faker): string
    {
        $count = $faker->numberBetween(1, 3);
        $values = [];
        for ($i = 0; $i < $count; $i++) {
            $values[] = '"' . $faker->word() . '"';
        }

        return '{' . implode(',', $values) . '}';
    }
}
