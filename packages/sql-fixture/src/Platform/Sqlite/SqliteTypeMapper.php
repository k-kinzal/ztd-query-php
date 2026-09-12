<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Type mapper for SQLite based on type affinity rules.
 *
 * SQLite uses "type affinity" where the declared type is mapped to one of:
 * - INTEGER: INT, INTEGER, TINYINT, SMALLINT, MEDIUMINT, BIGINT, INT2, INT8
 * - TEXT: CHAR, VARCHAR, TEXT, CLOB
 * - REAL: REAL, DOUBLE, FLOAT
 * - BLOB: BLOB or no type
 * - NUMERIC: DECIMAL, BOOLEAN, DATE, DATETIME, NUMERIC
 */
final class SqliteTypeMapper implements TypeMapperInterface
{
    /**
     * Generates fixture data according to the supplied schema or plan.
     */
    public function generate(Generator $faker, ColumnDefinition $column): mixed
    {
        if ($column->autoIncrement || $column->generated) {
            return null;
        }

        $value = (new Value\ColumnGenerator())->generateValue($faker, $column);

        if ($column->nullable) {
            $shouldUseDefault = $faker->boolean(10);
            if ($shouldUseDefault) {
                return $column->default;
            }
        }

        return $value;
    }
















}
