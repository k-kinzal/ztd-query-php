<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use Faker\Generator;

/**
 * Generates schema-aware SQL literals, projections and primary-key predicates.
 */
final class SqlExpressionGenerator
{
    /**
     * Use the seeded generator shared by the SQL scenario.
     */
    public function __construct(private Generator $faker)
    {
    }

    /**
     * Generate a predicate using every column of the primary key.
     */
    public function buildPkWhere(SchemaDefinition $schema): string
    {
        $conditions = [];
        foreach ($schema->primaryKeys as $pk) {
            $literal = $this->generateLiteral($pk, $schema);
            $conditions[] = "`$pk` = $literal";
        }
        return implode(' AND ', $conditions);
    }

    /**
     * @param array<int, string> $columns
     */
    public function randomColumns(array $columns): string
    {
        $count = $this->faker->numberBetween(1, count($columns));

        $selected = [];
        for ($index = 0; $index < $count; $index++) {
            $selected[] = array_splice($columns, $this->faker->numberBetween(0, count($columns) - 1), 1)[0];
        }
        return implode(', ', array_map(fn (string $c) => "`$c`", $selected));
    }

    /**
     * Generate a SQL literal compatible with the selected column.
     */
    public function generateLiteral(string $column, SchemaDefinition $schema): string
    {
        $col = strtolower($column);

        if (str_contains($col, 'id') || str_contains($col, 'quantity') ||
            str_contains($col, 'tinyint') || str_contains($col, 'smallint') ||
            str_contains($col, 'bigint') || str_contains($col, 'year') ||
            ($col === 'col_int')) {
            return (string) $this->faker->numberBetween(1, 100);
        }

        if (str_contains($col, 'float') || str_contains($col, 'double')) {
            return (string) round($this->faker->randomFloat(4, -1000, 1000), 4);
        }

        if (str_contains($col, 'decimal') || str_contains($col, 'amount') || str_contains($col, 'price')) {
            return (string) round($this->faker->randomFloat(2, 0, 9999), 2);
        }

        if (str_contains($col, 'date') && str_contains($col, 'time')) {
            return "'" . $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s') . "'";
        }
        if (str_contains($col, 'timestamp')) {
            return "'" . $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s') . "'";
        }
        if (str_contains($col, 'date')) {
            return "'" . $this->faker->date('Y-m-d') . "'";
        }
        if (str_contains($col, 'time')) {
            return "'" . $this->faker->time('H:i:s') . "'";
        }

        if (str_contains($col, 'json')) {
            $key = $this->faker->word();
            $val = $this->faker->word();
            return "'{\"" . addslashes($key) . '":"' . addslashes($val) . "\"}'";
        }

        if (str_contains($col, 'enum')) {

            $choice = $this->chooseColumn(['a', 'b', 'c']);
            return "'" . $choice . "'";
        }

        if (str_contains($col, 'set')) {

            $selected = array_slice(['x', 'y', 'z'], 0, $this->faker->numberBetween(1, 3));
            return "'" . implode(',', $selected) . "'";
        }

        $str = $this->faker->lexify('????');
        return "'" . addslashes($str) . "'";
    }

    /**
     * Determine whether a fixture column accepts quoted textual values.
     */
    public function isTextColumn(string $column): bool
    {
        $column = strtolower($column);

        return str_contains($column, 'name')
            || str_contains($column, 'email')
            || str_contains($column, 'status')
            || str_contains($column, 'text')
            || str_contains($column, 'varchar')
            || str_contains($column, 'char');
    }

    /**
     * Choose a typed column name using the scenario's seeded generator.
     *
     * @param non-empty-array<int, string> $columns
     */
    public function chooseColumn(array $columns): string
    {
        $keys = array_keys($columns);
        return $columns[$keys[$this->faker->numberBetween(0, count($keys) - 1)]];
    }
}
