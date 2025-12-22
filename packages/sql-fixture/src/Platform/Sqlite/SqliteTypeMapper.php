<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Type mapper for SQLite based on type affinity rules.
 *
 * SQLite uses "type affinity" where the declared type is mapped to one of:
 * - INTEGER: INT, INTEGER, TINYINT, SMALLINT, MEDIUMINT, BIGINT, INT2, INT8
 * - TEXT: CHAR, VARCHAR, TEXT, CLOB
 * - REAL: REAL, DOUBLE, FLOAT
 * - BLOB: BLOB or no type
 * - NUMERIC: DECIMAL, BOOLEAN, DATE, DATETIME, NUMERIC
 */
final class SqliteTypeMapper implements TypeMapperInterface
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
        $affinity = $this->determineAffinity($type);

        return match ($affinity) {
            'INTEGER' => $this->generateInteger($faker, $column),
            'TEXT' => $this->generateText($faker, $column),
            'REAL' => $this->generateReal($faker, $column),
            'BLOB' => $this->generateBlob($faker, $column),
            'NUMERIC' => $this->generateNumeric($faker, $column),
            default => $faker->text(50),
        };
    }

    /**
     * Determine SQLite type affinity based on declared type name.
     * See: https://www.sqlite.org/datatype3.html#type_affinity
     */
    private function determineAffinity(string $type): string
    {
        if (str_contains($type, 'INT')) {
            return 'INTEGER';
        }

        if (str_contains($type, 'CHAR') || str_contains($type, 'CLOB') || str_contains($type, 'TEXT')) {
            return 'TEXT';
        }

        if ($type === '' || str_contains($type, 'BLOB')) {
            return 'BLOB';
        }

        if (str_contains($type, 'REAL') || str_contains($type, 'FLOA') || str_contains($type, 'DOUB')) {
            return 'REAL';
        }

        return 'NUMERIC';
    }

    private function generateInteger(Generator $faker, ColumnDefinition $column): int
    {
        $type = strtoupper($column->type);

        return match (true) {
            str_contains($type, 'TINYINT') => $faker->numberBetween(-128, 127),
            str_contains($type, 'SMALLINT'), str_contains($type, 'INT2') => $faker->numberBetween(-32768, 32767),
            str_contains($type, 'MEDIUMINT') => $faker->numberBetween(-8388608, 8388607),
            str_contains($type, 'BIGINT'), str_contains($type, 'INT8') => $faker->numberBetween(PHP_INT_MIN, PHP_INT_MAX),
            default => $faker->numberBetween(-2147483648, 2147483647),
        };
    }

    private function generateText(Generator $faker, ColumnDefinition $column): string
    {
        $type = strtoupper($column->type);
        $length = $column->length;

        if (str_contains($type, 'CHAR') && $length !== null) {
            $pattern = str_repeat('?', $length);
            $result = $faker->lexify($pattern);
            return substr($result, 0, $length);
        }

        if ($length !== null) {
            $text = $faker->text(min($length, 200));
            return substr($text, 0, $length);
        }

        /** @var string $result */
        $result = match (true) {
            str_contains($type, 'TINYTEXT') => substr($faker->text(255), 0, 255),
            str_contains($type, 'MEDIUMTEXT') => $faker->paragraphs(3, true),
            str_contains($type, 'LONGTEXT'), str_contains($type, 'CLOB') => $faker->paragraphs(5, true),
            default => $faker->paragraphs(2, true),
        };
        return $result;
    }

    private function generateReal(Generator $faker, ColumnDefinition $column): float
    {
        $type = strtoupper($column->type);

        if ($column->precision !== null && $column->scale !== null) {
            $integerDigits = $column->precision - $column->scale;
            $max = (float) pow(10, $integerDigits) - 1;
            return $faker->randomFloat($column->scale, -$max, $max);
        }

        return match (true) {
            str_contains($type, 'FLOAT') => $faker->randomFloat(2, -1000.0, 1000.0),
            default => $faker->randomFloat(4, -1000000.0, 1000000.0),
        };
    }

    private function generateBlob(Generator $faker, ColumnDefinition $column): string
    {
        $length = $column->length;

        if ($length !== null) {
            return random_bytes(max(1, $length));
        }

        return random_bytes(max(1, $faker->numberBetween(1, 1000)));
    }

    private function generateNumeric(Generator $faker, ColumnDefinition $column): mixed
    {
        $type = strtoupper($column->type);

        return match (true) {
            str_contains($type, 'BOOL') => $faker->boolean() ? 1 : 0,

            str_contains($type, 'DATETIME'), str_contains($type, 'TIMESTAMP') =>
                $faker->dateTime()->format('Y-m-d H:i:s'),
            $type === 'DATE' => $faker->date('Y-m-d'),
            $type === 'TIME' => $faker->time('H:i:s'),

            str_contains($type, 'DECIMAL'), str_contains($type, 'NUMERIC') =>
                $this->generateDecimal($faker, $column),

            default => $faker->randomFloat(2, -1000.0, 1000.0),
        };
    }

    private function generateDecimal(Generator $faker, ColumnDefinition $column): float
    {
        $precision = $column->precision ?? 10;
        $scale = $column->scale ?? 0;
        $integerDigits = $precision - $scale;

        $max = (float) pow(10, $integerDigits) - 1;

        return $faker->randomFloat($scale, -$max, $max);
    }
}
