<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Value;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;

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
    public function generateValue(Generator $faker, ColumnDefinition $column): int|float|bool|string|null
    {
        $type = strtoupper($column->type);

        if (in_array($type, ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT', 'FLOAT', 'DOUBLE', 'REAL', 'DECIMAL', 'NUMERIC', 'DEC', 'FIXED', 'BIT'], true)) {
            return (new NumericGenerator())->generate($faker, $column);
        }

        if (in_array($type, ['CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'BINARY', 'VARBINARY', 'TINYBLOB', 'BLOB', 'MEDIUMBLOB', 'LONGBLOB', 'ENUM', 'SET'], true)) {
            return (new TextGenerator())->generate($faker, $column);
        }

        if (in_array($type, ['POINT', 'LINESTRING', 'POLYGON', 'MULTIPOINT', 'MULTILINESTRING', 'MULTIPOLYGON', 'GEOMETRY', 'GEOMETRYCOLLECTION'], true)) {
            return (new GeometryGenerator())->generate($faker, $column);
        }

        return match ($type) {

            'DATE' => $faker->date('Y-m-d'),
            'TIME' => $faker->time('H:i:s'),
            'DATETIME' => $faker->dateTime()->format('Y-m-d H:i:s'),
            'TIMESTAMP' => $faker->dateTimeBetween('1970-01-01', '2038-01-19')->format('Y-m-d H:i:s'),
            'YEAR' => $faker->numberBetween(1901, 2155),

            'JSON' => json_encode([
                'key' => $faker->text(20),
                'value' => $faker->numberBetween(1, 100),
            ]),

            'BOOL', 'BOOLEAN' => $faker->boolean(),

            default => $faker->text(50),
        };
    }
}
