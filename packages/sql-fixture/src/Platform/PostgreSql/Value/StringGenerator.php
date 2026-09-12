<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Value;

use Faker\Generator;
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
}
