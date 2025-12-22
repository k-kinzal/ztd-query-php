<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use Faker\Generator;

final class SchemaDefinition
{
    /** @var array<int, string> */
    public readonly array $columns;

    /** @var array<int, string> */
    public readonly array $primaryKeys;

    /**
     * @param array<int, string> $columns
     * @param array<int, string> $primaryKeys
     */
    public function __construct(
        public readonly string $name,
        public readonly string $sql,
        array $columns,
        array $primaryKeys
    ) {
        $this->columns = $columns;
        $this->primaryKeys = $primaryKeys;
    }
}

final class SchemaPool
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
            'CREATE TABLE basic (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255), status VARCHAR(50))',
            ['id', 'name', 'email', 'status'],
            ['id']
        );

        self::$schemas['numeric_types'] = new SchemaDefinition(
            'numeric_types',
            'CREATE TABLE numeric_types (id INT PRIMARY KEY, col_tinyint TINYINT, col_smallint SMALLINT, col_int INT, col_bigint BIGINT, col_float FLOAT, col_double DOUBLE, col_decimal DECIMAL(10,2))',
            ['id', 'col_tinyint', 'col_smallint', 'col_int', 'col_bigint', 'col_float', 'col_double', 'col_decimal'],
            ['id']
        );

        self::$schemas['string_types'] = new SchemaDefinition(
            'string_types',
            "CREATE TABLE string_types (id INT PRIMARY KEY, col_char CHAR(10), col_varchar VARCHAR(255), col_text TEXT, col_enum ENUM('a','b','c'), col_set SET('x','y','z'))",
            ['id', 'col_char', 'col_varchar', 'col_text', 'col_enum', 'col_set'],
            ['id']
        );

        self::$schemas['temporal_types'] = new SchemaDefinition(
            'temporal_types',
            'CREATE TABLE temporal_types (id INT PRIMARY KEY, col_date DATE, col_time TIME, col_datetime DATETIME, col_timestamp TIMESTAMP NULL, col_year YEAR)',
            ['id', 'col_date', 'col_time', 'col_datetime', 'col_timestamp', 'col_year'],
            ['id']
        );

        self::$schemas['composite_pk'] = new SchemaDefinition(
            'composite_pk',
            'CREATE TABLE composite_pk (order_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL DEFAULT 1, PRIMARY KEY (order_id, product_id))',
            ['order_id', 'product_id', 'quantity'],
            ['order_id', 'product_id']
        );

        self::$schemas['nullable_heavy'] = new SchemaDefinition(
            'nullable_heavy',
            'CREATE TABLE nullable_heavy (id INT PRIMARY KEY, col_varchar VARCHAR(255), col_int INT, col_decimal DECIMAL(10,2), col_date DATE)',
            ['id', 'col_varchar', 'col_int', 'col_decimal', 'col_date'],
            ['id']
        );

        self::$schemas['json_type'] = new SchemaDefinition(
            'json_type',
            'CREATE TABLE json_type (id INT PRIMARY KEY, col_json JSON)',
            ['id', 'col_json'],
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
