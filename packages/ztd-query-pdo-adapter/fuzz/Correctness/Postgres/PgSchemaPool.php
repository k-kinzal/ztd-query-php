<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Postgres;

use Faker\Generator;
use Fuzz\Correctness\SchemaDefinition;

final class PgSchemaPool
{
    /** @var array<string, SchemaDefinition> */
    private static array $schemas = [];

    private static bool $initialized = false;

    private static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$schemas['basic'] = new SchemaDefinition(
            'basic',
            'CREATE TABLE basic (id SERIAL PRIMARY KEY, name TEXT NOT NULL, email TEXT, status TEXT)',
            ['id', 'name', 'email', 'status'],
            ['id']
        );

        self::$schemas['numeric_types'] = new SchemaDefinition(
            'numeric_types',
            'CREATE TABLE numeric_types (id SERIAL PRIMARY KEY, col_smallint SMALLINT, col_int INTEGER, col_bigint BIGINT, col_real REAL, col_double DOUBLE PRECISION, col_numeric NUMERIC(10,2))',
            ['id', 'col_smallint', 'col_int', 'col_bigint', 'col_real', 'col_double', 'col_numeric'],
            ['id']
        );

        self::$schemas['text_types'] = new SchemaDefinition(
            'text_types',
            'CREATE TABLE text_types (id SERIAL PRIMARY KEY, col_text TEXT, col_varchar VARCHAR(255), col_char CHAR(10))',
            ['id', 'col_text', 'col_varchar', 'col_char'],
            ['id']
        );

        self::$schemas['boolean_type'] = new SchemaDefinition(
            'boolean_type',
            'CREATE TABLE boolean_type (id SERIAL PRIMARY KEY, col_bool BOOLEAN, col_text TEXT)',
            ['id', 'col_bool', 'col_text'],
            ['id']
        );

        self::$schemas['composite_pk'] = new SchemaDefinition(
            'composite_pk',
            'CREATE TABLE composite_pk (order_id INTEGER NOT NULL, product_id INTEGER NOT NULL, quantity INTEGER NOT NULL DEFAULT 1, PRIMARY KEY (order_id, product_id))',
            ['order_id', 'product_id', 'quantity'],
            ['order_id', 'product_id']
        );

        self::$schemas['nullable_heavy'] = new SchemaDefinition(
            'nullable_heavy',
            'CREATE TABLE nullable_heavy (id SERIAL PRIMARY KEY, col_text TEXT, col_int INTEGER, col_numeric NUMERIC(10,2))',
            ['id', 'col_text', 'col_int', 'col_numeric'],
            ['id']
        );

        self::$initialized = true;
    }

    public static function random(Generator $faker): SchemaDefinition
    {
        self::initialize();
        $keys = array_keys(self::$schemas);
        /** @var string $key */
        $key = $faker->randomElement($keys);
        return self::$schemas[$key];
    }

    /**
     * @return array<string, SchemaDefinition>
     */
    public static function all(): array
    {
        self::initialize();
        return self::$schemas;
    }

    public static function get(string $name): SchemaDefinition
    {
        self::initialize();
        if (!isset(self::$schemas[$name])) {
            throw new \InvalidArgumentException("Unknown schema: $name");
        }
        return self::$schemas[$name];
    }
}
