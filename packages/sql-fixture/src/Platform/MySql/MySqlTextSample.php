<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Picks text a MySQL character column will hold.
 *
 * A declared length is a limit the server enforces, so text is cut to it
 * rather than offered in the hope that it fits.
 */
final class MySqlTextSample
{
    /**
     * Picks text exactly as long as a CHAR column is declared.
     *
     * CHAR is padded to its length on the way in, so generating it at that
     * length is what a round trip will report back.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the text is for
     *
     * @return string Text of the declared length
     */
    public function char(Generator $faker, ColumnDefinition $column): string
    {
        $length = $column->length ?? 1;

        return substr($faker->lexify(str_repeat('?', $length)), 0, $length);
    }

    /**
     * Picks text no longer than a VARCHAR column allows.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the text is for
     *
     * @return string Text within the declared length
     */
    public function varchar(Generator $faker, ColumnDefinition $column): string
    {
        $maxLength = $column->length ?? 255;

        return substr($faker->text(min($maxLength, 200)), 0, $maxLength);
    }
}
