<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Value;

use Faker\Generator;
use LogicException;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Generates character, binary and enumerated column values.
 *
 * @visibility root
 */
final class StringGenerator
{
    /**
     * Generates char.
     */
    public function generateChar(Generator $faker, ColumnDefinition $column): string
    {
        $length = $column->length ?? 1;
        $pattern = str_repeat('?', $length);
        $result = $faker->lexify($pattern);
        return substr($result, 0, $length);
    }

    /**
     * Generates varchar.
     */
    public function generateVarchar(Generator $faker, ColumnDefinition $column): string
    {
        $maxLength = $column->length ?? 255;
        $text = $faker->text(min($maxLength, 200));
        return substr($text, 0, $maxLength);
    }

    /**
     * Generates binary.
     */
    public function generateBinary(Generator $faker, ColumnDefinition $column): string
    {
        unset($faker);
        $length = max(1, $column->length ?? 1);
        return random_bytes($length);
    }

    /**
     * Generates varbinary.
     */
    public function generateVarbinary(Generator $faker, ColumnDefinition $column): string
    {
        $maxLength = max(1, $column->length ?? 255);
        $length = max(1, $faker->numberBetween(1, $maxLength));
        return random_bytes($length);
    }

    /**
     * Generates enum.
     */
    public function generateEnum(Generator $faker, ColumnDefinition $column): ?string
    {
        $values = $column->enumValues ?? [];
        if ($values === []) {
            return null;
        }
        /**
         * @var string $element
         */
        $element = $faker->randomElement($values);
        return $element;
    }

    /**
     * Generates set.
     * @throws LogicException
     */
    public function generateSet(Generator $faker, ColumnDefinition $column): ?string
    {
        $values = $column->enumValues ?? [];
        if ($values === []) {
            return null;
        }
        $count = $faker->numberBetween(1, count($values));
        $selected = [];
        foreach ($faker->randomElements($values, $count) as $value) {
            if (!is_string($value)) {
                throw new LogicException('Faker returned a non-string SET value.');
            }
            $selected[] = $value;
        }
        return implode(',', $selected);
    }
}
