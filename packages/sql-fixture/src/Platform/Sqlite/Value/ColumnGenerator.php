<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Value;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\IntegerRange;
use SqlFixture\TypeMapper\IntegerWidth;

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
    public function generateValue(Generator $faker, ColumnDefinition $column): int|float|string
    {
        $type = strtoupper($column->type);
        $affinity = (new TypeAffinity())->determineAffinity($type);

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
     * Generates integer.
     */
    public function generateInteger(Generator $faker, ColumnDefinition $column): int
    {
        $type = strtoupper($column->type);

        $width = match (true) {
            str_contains($type, 'TINYINT') => IntegerWidth::Bits8,
            str_contains($type, 'SMALLINT'), str_contains($type, 'INT2') => IntegerWidth::Bits16,
            str_contains($type, 'MEDIUMINT') => IntegerWidth::Bits24,
            str_contains($type, 'BIGINT'), str_contains($type, 'INT8') => IntegerWidth::Bits64,
            default => IntegerWidth::Bits32,
        };
        $range = new IntegerRange($width);
        return $faker->numberBetween($range->minimum, $range->maximum);
    }

    /**
     * Generates text.
     */
    public function generateText(Generator $faker, ColumnDefinition $column): string
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

        /**
         * @var string $result
         */
        $result = match (true) {
            str_contains($type, 'TINYTEXT') => substr($faker->text(255), 0, 255),
            str_contains($type, 'MEDIUMTEXT') => (new \SqlFixture\TypeMapper\ParagraphGenerator())->generate($faker, 3),
            str_contains($type, 'LONGTEXT'), str_contains($type, 'CLOB') => (new \SqlFixture\TypeMapper\ParagraphGenerator())->generate($faker, 5),
            default => (new \SqlFixture\TypeMapper\ParagraphGenerator())->generate($faker, 2),
        };
        return $result;
    }

    /**
     * Generates real.
     */
    public function generateReal(Generator $faker, ColumnDefinition $column): float
    {
        $type = strtoupper($column->type);

        if ($column->precision !== null && $column->scale !== null) {
            $range = new \SqlFixture\TypeMapper\DecimalRange($column);
            return $faker->randomFloat($range->scale, $range->minimum, $range->maximum);
        }

        return match (true) {
            str_contains($type, 'FLOAT') => $faker->randomFloat(2, -1000.0, 1000.0),
            default => $faker->randomFloat(4, -1000000.0, 1000000.0),
        };
    }

    /**
     * Generates blob.
     */
    public function generateBlob(Generator $faker, ColumnDefinition $column): string
    {
        $length = $column->length;

        if ($length !== null) {
            return random_bytes(max(1, $length));
        }

        return random_bytes(max(1, $faker->numberBetween(1, 1000)));
    }

    /**
     * Generates numeric.
     */
    public function generateNumeric(Generator $faker, ColumnDefinition $column): int|float|string
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

    /**
     * Generates decimal.
     */
    public function generateDecimal(Generator $faker, ColumnDefinition $column): float
    {
        $range = new \SqlFixture\TypeMapper\DecimalRange($column);
        return $faker->randomFloat($range->scale, $range->minimum, $range->maximum);
    }
}
