<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use Faker\Generator;
use Random\RandomException;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Picks bytes a MySQL binary column will hold.
 *
 * Binary columns hold arbitrary bytes rather than text, so the bytes are drawn
 * from the system's own source rather than from a character alphabet. BINARY
 * is padded to its declared length and VARBINARY is not, which is the only
 * difference between the two here.
 */
final class MySqlBinarySample
{
    /**
     * Picks exactly as many bytes as a BINARY column is declared to hold.
     *
     * @param ColumnDefinition $column Column the bytes are for
     *
     * @return string Bytes of the declared length
     *
     * @throws RandomException When the system has no source of randomness
     */
    public function binary(ColumnDefinition $column): string
    {
        return random_bytes(max(1, $column->length ?? 1));
    }

    /**
     * Picks up to as many bytes as a VARBINARY column allows.
     *
     * @param Generator $faker Source of the choice of length
     * @param ColumnDefinition $column Column the bytes are for
     *
     * @return string Bytes within the declared length
     *
     * @throws RandomException When the system has no source of randomness
     */
    public function varbinary(Generator $faker, ColumnDefinition $column): string
    {
        return random_bytes(max(1, $faker->numberBetween(1, max(1, $column->length ?? 255))));
    }

    /**
     * Picks up to as many bytes as one of the BLOB types holds.
     *
     * @param Generator $faker Source of the choice of length
     * @param int $maxLength Longest the value may be
     *
     * @return string Bytes within that length
     *
     * @throws RandomException When the system has no source of randomness
     */
    public function blob(Generator $faker, int $maxLength): string
    {
        return random_bytes(max(1, $faker->numberBetween(1, $maxLength)));
    }
}
