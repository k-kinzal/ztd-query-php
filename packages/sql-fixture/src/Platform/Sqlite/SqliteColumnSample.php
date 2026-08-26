<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use Faker\Generator;
use Random\RandomException;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Picks a value of the kind a SQLite column's affinity calls for.
 *
 * SQLite would accept almost anything in almost any column, so what makes a
 * fixture useful is that it looks like what the schema author meant. The
 * affinity decides the storage class, and the declared type name is still read
 * within it — a column declared TINYINT is an integer column, but a fixture
 * that put 40000 in it would not be what its author had in mind.
 */
final class SqliteColumnSample
{
    /**
     * The shortest text Faker will produce.
     *
     * Asking for less raises rather than answering, so a column declared
     * narrower than this is filled from a longer draw and cut down.
     */
    private const SHORTEST_FAKER_TEXT = 5;

    /**
     * Picks a value of the kind the column's affinity calls for.
     *
     * @param Generator $faker Source of every choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int|float|string A value of that kind
     *
     * @throws RandomException When a blob column is asked for and the system has no source of randomness
     */
    public function of(Generator $faker, ColumnDefinition $column): int|float|string
    {
        return match (SqliteAffinity::of($column->type)) {
            SqliteAffinity::Integer => $this->integer($faker, $column),
            SqliteAffinity::Text => $this->text($faker, $column),
            SqliteAffinity::Real => $this->real($faker, $column),
            SqliteAffinity::Blob => $this->blob($faker, $column),
            SqliteAffinity::Numeric => $this->numeric($faker, $column),
        };
    }

    /**
     * Picks a whole number in the range the declared type suggests.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int A number the schema author would expect
     */
    public function integer(Generator $faker, ColumnDefinition $column): int
    {
        $type = strtoupper($column->type);

        return match (true) {
            str_contains($type, 'TINYINT') => $faker->numberBetween(-128, 127),
            str_contains($type, 'SMALLINT'), str_contains($type, 'INT2') => $faker->numberBetween(-32768, 32767),
            str_contains($type, 'MEDIUMINT') => $faker->numberBetween(-8388608, 8388607),
            str_contains($type, 'BIGINT'), str_contains($type, 'INT8') => $faker->numberBetween(
                PHP_INT_MIN,
                PHP_INT_MAX,
            ),
            default => $faker->numberBetween(-2147483648, 2147483647),
        };
    }

    /**
     * Picks text of the length the declared type suggests.
     *
     * A declared length is honoured even though SQLite does not enforce it,
     * because it is the only statement the author made about how long the
     * value should be. A CHAR of that length is filled exactly; anything else
     * is cut to it.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the text is for
     *
     * @return string Text the schema author would expect
     */
    public function text(Generator $faker, ColumnDefinition $column): string
    {
        $type = strtoupper($column->type);
        $length = $column->length;
        if ($length !== null && str_contains($type, 'CHAR')) {
            return substr($faker->lexify(str_repeat('?', $length)), 0, $length);
        }
        if ($length !== null) {
            return substr($faker->text(max(self::SHORTEST_FAKER_TEXT, min($length, 200))), 0, $length);
        }

        /** @var string $written */
        $written = match (true) {
            str_contains($type, 'TINYTEXT') => substr($faker->text(255), 0, 255),
            str_contains($type, 'MEDIUMTEXT') => $this->paragraphs($faker, 3),
            str_contains($type, 'LONGTEXT'), str_contains($type, 'CLOB') => $this->paragraphs($faker, 5),
            default => $this->paragraphs($faker, 2),
        };

        return $written;
    }

    /**
     * Picks a floating-point number of the size the declared type suggests.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return float A number the schema author would expect
     */
    public function real(Generator $faker, ColumnDefinition $column): float
    {
        if ($column->precision !== null && $column->scale !== null) {
            $max = (float) pow(10, $column->precision - $column->scale) - 1;

            return $faker->randomFloat($column->scale, -$max, $max);
        }

        return str_contains(strtoupper($column->type), 'FLOAT')
            ? $faker->randomFloat(2, -1000.0, 1000.0)
            : $faker->randomFloat(4, -1000000.0, 1000000.0);
    }

    /**
     * Picks bytes of the length the declared type suggests.
     *
     * @param Generator $faker Source of the choice of length
     * @param ColumnDefinition $column Column the bytes are for
     *
     * @return string Bytes the schema author would expect
     *
     * @throws RandomException When the system has no source of randomness
     */
    public function blob(Generator $faker, ColumnDefinition $column): string
    {
        return random_bytes(max(1, $column->length ?? $faker->numberBetween(1, 1000)));
    }

    /**
     * Picks a value for a column SQLite files under numeric affinity.
     *
     * Numeric affinity is where SQLite puts everything it did not recognise,
     * which is also where booleans, dates and times land — SQLite has no type
     * for any of them. The declared name is therefore the only thing that says
     * what the author meant, so it is read again here.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int|float|string A value the schema author would expect
     */
    public function numeric(Generator $faker, ColumnDefinition $column): int|float|string
    {
        $type = strtoupper($column->type);

        return match (true) {
            str_contains($type, 'BOOL') => $faker->boolean() ? 1 : 0,
            str_contains($type, 'DATETIME'), str_contains($type, 'TIMESTAMP') => $faker->dateTime()
                ->format('Y-m-d H:i:s'),
            $type === 'DATE' => $faker->date('Y-m-d'),
            $type === 'TIME' => $faker->time('H:i:s'),
            str_contains($type, 'DECIMAL'), str_contains($type, 'NUMERIC') => $this->decimal($faker, $column),
            default => $faker->randomFloat(2, -1000.0, 1000.0),
        };
    }

    /**
     * Picks a value the declared precision and scale allow.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return float A number within the declared precision
     */
    public function decimal(Generator $faker, ColumnDefinition $column): float
    {
        $precision = $column->precision ?? 10;
        $scale = $column->scale ?? 0;
        $max = (float) pow(10, $precision - $scale) - 1;

        return $faker->randomFloat($scale, -$max, $max);
    }

    /**
     * Draws several paragraphs as one block of text.
     *
     * Faker answers either the list of paragraphs or the joined text depending
     * on a flag, so the joining is done here, where the result is known to be
     * text rather than a list of it.
     *
     * @param Generator $faker Source of the text
     * @param int $count How many paragraphs to draw
     *
     * @return string The paragraphs, separated by blank lines
     */
    public function paragraphs(Generator $faker, int $count): string
    {
        $paragraphs = $faker->paragraphs($count);

        return is_array($paragraphs)
            ? implode("\n\n", array_filter($paragraphs, 'is_string'))
            : $paragraphs;
    }
}
