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
        return match (strtoupper($column->type)) {
            'TINYINT' => $this->numbers->tinyInt($faker, $column),
            'SMALLINT' => $this->numbers->smallInt($faker, $column),
            'MEDIUMINT' => $this->numbers->mediumInt($faker, $column),
            'INT', 'INTEGER' => $this->numbers->int($faker, $column),
            'BIGINT' => $this->numbers->bigInt($faker, $column),
            'FLOAT' => $faker->randomFloat(2, -1000.0, 1000.0),
            'DOUBLE', 'REAL' => $faker->randomFloat(4, -1000000.0, 1000000.0),
            'DECIMAL', 'NUMERIC', 'DEC', 'FIXED' => $this->numbers->decimal($faker, $column),
            'BIT' => $this->numbers->bit($faker, $column),

            'CHAR' => $this->text->char($faker, $column),
            'VARCHAR' => $this->text->varchar($faker, $column),
            'TINYTEXT' => substr($faker->text(255), 0, 255),
            'TEXT' => $this->paragraphs($faker, 2),
            'MEDIUMTEXT' => $this->paragraphs($faker, 3),
            'LONGTEXT' => $this->paragraphs($faker, 5),

            'BINARY' => $this->bytes->binary($column),
            'VARBINARY' => $this->bytes->varbinary($faker, $column),
            'TINYBLOB' => $this->bytes->blob($faker, 255),
            'BLOB', 'MEDIUMBLOB', 'LONGBLOB' => $this->bytes->blob($faker, 1000),

            'ENUM' => $this->members->one($faker, $column),
            'SET' => $this->members->some($faker, $column),

            'DATE' => $faker->date('Y-m-d'),
            'TIME' => $faker->time('H:i:s'),
            'DATETIME' => $faker->dateTime()->format('Y-m-d H:i:s'),
            'TIMESTAMP' => $faker->dateTimeBetween('1970-01-01', '2038-01-19')->format('Y-m-d H:i:s'),
            'YEAR' => $faker->numberBetween(1901, 2155),

            'JSON' => $this->json($faker),

            'POINT', 'GEOMETRY' => $this->geometry->point($faker),
            'LINESTRING' => $this->geometry->lineString($faker),
            'POLYGON' => $this->geometry->polygon($faker),
            'MULTIPOINT' => $this->geometry->multiPoint($faker),
            'MULTILINESTRING' => $this->geometry->multiLineString($faker),
            'MULTIPOLYGON' => $this->geometry->multiPolygon($faker),
            'GEOMETRYCOLLECTION' => $this->geometry->collection($faker),

            'BOOL', 'BOOLEAN' => $faker->boolean(),

            default => $faker->text(50),
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
