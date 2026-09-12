<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Value;

use Faker\Generator;

/**
 * Formats PostgreSQL binary, interval, JSON and array values.
 *
 * @visibility root
 */
final class StructuredGenerator
{
    /**
     * Generates bytea.
     */
    public function generateBytea(Generator $faker): string
    {
        $length = max(1, $faker->numberBetween(1, 100));
        return '\\x' . bin2hex(random_bytes($length));
    }

    /**
     * Generates interval.
     */
    public function generateInterval(Generator $faker): string
    {
        $units = ['days', 'hours', 'minutes', 'seconds', 'months', 'years'];
        /**
         * @var string $unit
         */
        $unit = $faker->randomElement($units);
        $value = $faker->numberBetween(1, 30);

        return "{$value} {$unit}";
    }

    /**
     * Generates json.
     */
    public function generateJson(Generator $faker): string
    {
        $json = json_encode([
            'key' => $faker->text(20),
            'value' => $faker->numberBetween(1, 100),
        ]);

        return $json !== false ? $json : '{}';
    }

    /**
     * Generates int array.
     */
    public function generateIntArray(Generator $faker): string
    {
        $count = $faker->numberBetween(1, 5);
        $values = [];
        for ($i = 0; $i < $count; $i++) {
            $values[] = (string) $faker->numberBetween(1, 1000);
        }

        return '{' . implode(',', $values) . '}';
    }

    /**
     * Generates text array.
     */
    public function generateTextArray(Generator $faker): string
    {
        $count = $faker->numberBetween(1, 3);
        $values = [];
        for ($i = 0; $i < $count; $i++) {
            $values[] = '"' . $faker->word() . '"';
        }

        return '{' . implode(',', $values) . '}';
    }
}
