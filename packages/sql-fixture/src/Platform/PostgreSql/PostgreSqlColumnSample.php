<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Picks a value of the kind a PostgreSQL column's type calls for.
 *
 * PostgreSQL takes several types no other server has, and each has a spelling
 * it will not accept a substitute for: bytea is hex behind a backslash-x,
 * an interval is a number and a unit, and an array is braces around
 * comma-separated members with text members quoted. Those spellings are what
 * this answers; whether the column should be given a value at all is the type
 * mapper's question.
 */
final class PostgreSqlColumnSample
{
    /**
     * The shortest text Faker will produce.
     *
     * Asking for less raises rather than answering, so a column declared
     * narrower than this is filled from a longer draw and cut down.
     */
    private const SHORTEST_FAKER_TEXT = 5;

    /**
     * Picks a value the column's declared type will accept.
     *
     * @param Generator $faker Source of every choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int|float|string|bool A value of the kind the type calls for
     *
     */
    public function of(Generator $faker, ColumnDefinition $column): int|float|string|bool
    {
        return match (strtoupper($column->type)) {
            'SMALLINT', 'INT2' => $faker->numberBetween(-32768, 32767),
            'INTEGER', 'INT', 'INT4' => $faker->numberBetween(-2147483648, 2147483647),
            'BIGINT', 'INT8' => $faker->numberBetween(PHP_INT_MIN, PHP_INT_MAX),

            'REAL', 'FLOAT4' => $faker->randomFloat(2, -1000.0, 1000.0),
            'DOUBLE PRECISION', 'FLOAT8' => $faker->randomFloat(4, -1000000.0, 1000000.0),

            'DECIMAL', 'NUMERIC', 'DEC' => $this->decimal($faker, $column),
            'MONEY' => $faker->randomFloat(2, 0.0, 99999.99),

            'BOOLEAN', 'BOOL' => $faker->boolean(),

            'CHAR', 'CHARACTER' => $this->char($faker, $column),
            'VARCHAR', 'CHARACTER VARYING' => $this->varchar($faker, $column),
            'TEXT' => $this->paragraphs($faker, 2),

            'BYTEA' => $this->bytea($faker),

            'DATE' => $faker->date('Y-m-d'),
            'TIME', 'TIME WITHOUT TIME ZONE' => $faker->time('H:i:s'),
            'TIME WITH TIME ZONE', 'TIMETZ' => $faker->time('H:i:sP'),
            'TIMESTAMP', 'TIMESTAMP WITHOUT TIME ZONE' => $faker->dateTime()->format('Y-m-d H:i:s'),
            'TIMESTAMP WITH TIME ZONE', 'TIMESTAMPTZ' => $faker->dateTime()->format('Y-m-d H:i:sP'),
            'INTERVAL' => $this->interval($faker),

            'JSON', 'JSONB' => $this->json($faker),

            'UUID' => $faker->uuid(),

            'INET' => $faker->ipv4(),
            'CIDR' => $faker->ipv4() . '/24',
            'MACADDR' => $faker->macAddress(),

            'INTEGER_ARRAY', 'INT_ARRAY' => $this->integerArray($faker),
            'TEXT_ARRAY' => $this->textArray($faker),

            'XML' => '<root>' . $faker->text(50) . '</root>',

            default => $faker->text(50),
        };
    }

    /**
     * Picks a value NUMERIC will accept.
     *
     * Precision counts every digit and scale counts the ones after the point,
     * so the digits before it are what is left, and that is what bounds the
     * value. An undeclared NUMERIC is NUMERIC(10,0).
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return float A value the column accepts
     */
    public function decimal(Generator $faker, ColumnDefinition $column): float
    {
        $precision = $column->precision ?? 10;
        $scale = $column->scale ?? 0;
        $max = (float) pow(10, $precision - $scale) - 1;

        return $faker->randomFloat($scale, -$max, $max);
    }

    /**
     * Picks text exactly as long as a CHARACTER column is declared.
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
     * Picks text no longer than a CHARACTER VARYING column allows.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the text is for
     *
     * @return string Text within the declared length
     */
    public function varchar(Generator $faker, ColumnDefinition $column): string
    {
        $maxLength = $column->length ?? 255;

        return substr($faker->text(max(self::SHORTEST_FAKER_TEXT, min($maxLength, 200))), 0, $maxLength);
    }

    /**
     * Writes bytes in the hex spelling bytea is read from.
     *
     * @param Generator $faker Source of the choice of length
     *
     * @return string Bytes as a bytea hex literal
     *
     */
    public function bytea(Generator $faker): string
    {
        return '\\x' . bin2hex(random_bytes(max(1, $faker->numberBetween(1, 100))));
    }

    /**
     * Writes a span of time in the spelling INTERVAL is read from.
     *
     * @param Generator $faker Source of the choice
     *
     * @return string An amount and the unit it counts
     */
    public function interval(Generator $faker): string
    {
        /** @var string $unit */
        $unit = $faker->randomElement(['days', 'hours', 'minutes', 'seconds', 'months', 'years']);

        return $faker->numberBetween(1, 30) . ' ' . $unit;
    }

    /**
     * Writes an object JSON and JSONB will both parse.
     *
     * @param Generator $faker Source of the choice
     *
     * @return string A JSON object, or an empty one when the text could not be encoded
     */
    public function json(Generator $faker): string
    {
        $encoded = json_encode(['key' => $faker->text(20), 'value' => $faker->numberBetween(1, 100)]);

        return $encoded !== false ? $encoded : '{}';
    }

    /**
     * Writes numbers in the brace spelling an integer array is read from.
     *
     * @param Generator $faker Source of the choice
     *
     * @return string An array literal of integers
     */
    public function integerArray(Generator $faker): string
    {
        $members = [];
        for ($index = 0; $index < $faker->numberBetween(1, 5); $index++) {
            $members[] = (string) $faker->numberBetween(1, 1000);
        }

        return '{' . implode(',', $members) . '}';
    }

    /**
     * Writes words in the brace spelling a text array is read from.
     *
     * Members of a text array are quoted, which is what keeps a member that
     * contains a comma from reading as two.
     *
     * @param Generator $faker Source of the choice
     *
     * @return string An array literal of quoted words
     */
    public function textArray(Generator $faker): string
    {
        $members = [];
        for ($index = 0; $index < $faker->numberBetween(1, 3); $index++) {
            $members[] = '"' . $faker->word() . '"';
        }

        return '{' . implode(',', $members) . '}';
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
