<?php

declare(strict_types=1);

namespace Tests\Fixture\Fixture;

use Faker\Factory;
use Faker\Generator;
use SqlFixture\Fixture\PlanGenerator;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\FixtureGenerator;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Schema\StaticSchemaResolver;

/**
 * The customer / order / order_detail / product schema the generator tests
 * are built on, and a seeded generator over it.
 */
final class ShopSchemas
{
    public static function generator(int $seed = 20260101): PlanGenerator
    {
        return new PlanGenerator(self::resolver(), new FixtureGenerator(self::faker($seed)), self::faker($seed));
    }

    /**
     * How many order_detail rows the plan produces across a spread of seeds,
     * which is how a range is observed rather than asserted one draw at a time.
     *
     * @return list<int>
     */
    public static function childCountsOverSeeds(string $plan, int $seeds = 40): array
    {
        $counts = [];

        foreach (range(1, $seeds) as $seed) {
            $counts[] = count(self::generator($seed)->generate(FixturePlan::from($plan))->rows('order_detail'));
        }

        return $counts;
    }

    public static function faker(int $seed = 20260101): Generator
    {
        $faker = Factory::create();
        $faker->seed($seed);

        return $faker;
    }

    public static function resolver(): StaticSchemaResolver
    {
        $parser = new MySqlSchemaParser();
        $resolver = new StaticSchemaResolver();

        foreach (self::ddl() as $ddl) {
            $resolver->register($parser->parse($ddl));
        }

        return $resolver;
    }

    /**
     * @return list<string>
     */
    public static function ddl(): array
    {
        return [
            'CREATE TABLE customer (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                tier ENUM("gold", "silver") NOT NULL
            )',
            'CREATE TABLE `order` (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                customer_id INT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL,
                total INT NOT NULL
            )',
            'CREATE TABLE order_detail (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                order_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NOT NULL,
                quantity INT NOT NULL
            )',
            'CREATE TABLE product (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL
            )',
            'CREATE TABLE order_shipping (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                order_id INT UNSIGNED NOT NULL,
                carrier VARCHAR(50) NOT NULL
            )',
            'CREATE TABLE audit_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                message VARCHAR(255) NOT NULL
            )',
        ];
    }
}
