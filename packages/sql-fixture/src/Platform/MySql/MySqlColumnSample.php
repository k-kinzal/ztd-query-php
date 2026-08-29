<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use Faker\Generator;
use LogicException;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Picks a value of the kind a MySQL column's type calls for.
 *
 * This answers only what shape the value takes; whether the column should be
 * given a value at all is the type mapper's question. The types divide into a
 * few subjects — numbers bounded by width or precision, text bounded by
 * length, arbitrary bytes, members a column enumerates, and geometries the
 * server parses as Well-Known Text — and each subject answers for itself.
 * A type nothing here names is treated as text, which every MySQL string type
 * will hold.
 */
final class MySqlColumnSample
{
    /**
     * @param MySqlNumberSample $numbers Picks numbers a numeric column accepts
     * @param MySqlTextSample $text Picks text a character column holds
     * @param MySqlBinarySample $bytes Picks bytes a binary column holds
     * @param MySqlEnumerationSample $members Picks from what an ENUM or SET declares
     * @param WellKnownTextGeometry $geometry Writes geometries the server parses
     */
    public function __construct(
        private readonly MySqlNumberSample $numbers = new MySqlNumberSample(),
        private readonly MySqlTextSample $text = new MySqlTextSample(),
        private readonly MySqlBinarySample $bytes = new MySqlBinarySample(),
        private readonly MySqlEnumerationSample $members = new MySqlEnumerationSample(),
        private readonly WellKnownTextGeometry $geometry = new WellKnownTextGeometry(),
    ) {
    }

    /**
     * Picks a value the column's declared type will accept.
     *
     * @param Generator $faker Source of every choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return int|float|string|bool|null A value of the kind the type calls for
     *
     * @throws LogicException When a chosen SET member is not a string
     */
    public function of(Generator $faker, ColumnDefinition $column): int|float|string|bool|null
    {
        $type = strtoupper($column->type);

        return match ($type) {
            'ENUM', 'SET' => $this->enumerated($faker, $column, $type),
            'JSON' => $this->json($faker),
            'BOOL', 'BOOLEAN' => $faker->boolean(),
            default => $this->numeric($faker, $column, $type)
                ?? $this->textual($faker, $column, $type)
                ?? $this->stored($faker, $column, $type)
                ?? $this->temporal($faker, $type)
                ?? $this->spatial($faker, $type)
                ?? $faker->text(50),
        };
    }

    /**
     * Picks a number, when the type is one that counts.
     *
     * TINYINT(1) is how MySQL writes a boolean, so this can answer with one.
     *
     * @param Generator $faker Source of every choice
     * @param ColumnDefinition $column Column the value is for
     * @param string $type The column's type, upper-cased
     *
     * @return int|float|bool|null A number the type accepts, or null when it is not a numeric type
     */
    public function numeric(Generator $faker, ColumnDefinition $column, string $type): int|float|bool|null
    {
        return match ($type) {
            'TINYINT' => $this->numbers->tinyInt($faker, $column),
            'SMALLINT' => $this->numbers->smallInt($faker, $column),
            'MEDIUMINT' => $this->numbers->mediumInt($faker, $column),
            'INT', 'INTEGER' => $this->numbers->int($faker, $column),
            'BIGINT' => $this->numbers->bigInt($faker, $column),
            'FLOAT' => $faker->randomFloat(2, -1000.0, 1000.0),
            'DOUBLE', 'REAL' => $faker->randomFloat(4, -1000000.0, 1000000.0),
            'DECIMAL', 'NUMERIC', 'DEC', 'FIXED' => $this->numbers->decimal($faker, $column),
            'BIT' => $this->numbers->bit($faker, $column),
            default => null,
        };
    }

    /**
     * Picks text, when the type is one that holds characters.
     *
     * @param Generator $faker Source of every choice
     * @param ColumnDefinition $column Column the value is for
     * @param string $type The column's type, upper-cased
     *
     * @return string|null Text the type accepts, or null when it is not a character type
     */
    public function textual(Generator $faker, ColumnDefinition $column, string $type): ?string
    {
        return match ($type) {
            'CHAR' => $this->text->char($faker, $column),
            'VARCHAR' => $this->text->varchar($faker, $column),
            'TINYTEXT' => substr($faker->text(255), 0, 255),
            'TEXT' => $this->paragraphs($faker, 2),
            'MEDIUMTEXT' => $this->paragraphs($faker, 3),
            'LONGTEXT' => $this->paragraphs($faker, 5),
            default => null,
        };
    }

    /**
     * Picks bytes, when the type is one that holds them unread.
     *
     * @param Generator $faker Source of every choice
     * @param ColumnDefinition $column Column the value is for
     * @param string $type The column's type, upper-cased
     *
     * @return string|null Bytes the type accepts, or null when it is not a binary type
     */
    public function stored(Generator $faker, ColumnDefinition $column, string $type): ?string
    {
        return match ($type) {
            'BINARY' => $this->bytes->binary($column),
            'VARBINARY' => $this->bytes->varbinary($faker, $column),
            'TINYBLOB' => $this->bytes->blob($faker, 255),
            'BLOB', 'MEDIUMBLOB', 'LONGBLOB' => $this->bytes->blob($faker, 1000),
            default => null,
        };
    }

    /**
     * Picks from what the column enumerates.
     *
     * ENUM holds one member and SET holds any number of them, so the two are
     * asked separately. A column that enumerates nothing has nothing to be
     * given, and that is what no value means here.
     *
     * @param Generator $faker Source of every choice
     * @param ColumnDefinition $column Column the value is for
     * @param string $type The column's type, upper-cased, either ENUM or SET
     *
     * @return string|null A member the column declares, or null when it declares none
     *
     * @throws LogicException When a chosen SET member is not a string
     */
    public function enumerated(Generator $faker, ColumnDefinition $column, string $type): ?string
    {
        return $type === 'ENUM'
            ? $this->members->one($faker, $column)
            : $this->members->some($faker, $column);
    }

    /**
     * Picks a moment, when the type is one that keeps time.
     *
     * @param Generator $faker Source of every choice
     * @param string $type The column's type, upper-cased
     *
     * @return int|string|null A moment written as the type keeps it, or null when it keeps no time
     */
    public function temporal(Generator $faker, string $type): int|string|null
    {
        return match ($type) {
            'DATE' => $faker->date('Y-m-d'),
            'TIME' => $faker->time('H:i:s'),
            'DATETIME' => $faker->dateTime()->format('Y-m-d H:i:s'),
            'TIMESTAMP' => $faker->dateTimeBetween('1970-01-01', '2038-01-19')->format('Y-m-d H:i:s'),
            'YEAR' => $faker->numberBetween(1901, 2155),
            default => null,
        };
    }

    /**
     * Writes a geometry, when the type is one the server parses as one.
     *
     * @param Generator $faker Source of every choice
     * @param string $type The column's type, upper-cased
     *
     * @return string|null Well-Known Text the server will parse, or null when the type is not spatial
     */
    public function spatial(Generator $faker, string $type): ?string
    {
        return match ($type) {
            'POINT', 'GEOMETRY' => $this->geometry->point($faker),
            'LINESTRING' => $this->geometry->lineString($faker),
            'POLYGON' => $this->geometry->polygon($faker),
            'MULTIPOINT' => $this->geometry->multiPoint($faker),
            'MULTILINESTRING' => $this->geometry->multiLineString($faker),
            'MULTIPOLYGON' => $this->geometry->multiPolygon($faker),
            'GEOMETRYCOLLECTION' => $this->geometry->collection($faker),
            default => null,
        };
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

    /**
     * Writes an object the server will parse as JSON.
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
}
