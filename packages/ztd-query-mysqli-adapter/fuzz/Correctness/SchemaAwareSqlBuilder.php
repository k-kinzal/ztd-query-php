<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use Faker\Generator;

final class SchemaAwareSqlBuilder
{
    private Generator $faker;

    public function __construct(Generator $faker)
    {
        $this->faker = $faker;
    }

    public function buildSelect(SchemaDefinition $schema): string
    {
        $table = $schema->name;
        $columns = $schema->columns;
        $variant = $this->faker->numberBetween(0, 4);

        switch ($variant) {
            case 0:
                return "SELECT * FROM `$table`";
            case 1:
                $cols = $this->randomColumns($columns);
                /** @var string $whereCol */
                $whereCol = $this->faker->randomElement($columns);
                $literal = $this->generateLiteral($whereCol, $schema);
                return "SELECT $cols FROM `$table` WHERE `$whereCol` = $literal";
            case 2:
                $cols = $this->randomColumns($columns);
                /** @var string $orderCol */
                $orderCol = $this->faker->randomElement($columns);
                $limit = $this->faker->numberBetween(1, 10);
                return "SELECT $cols FROM `$table` ORDER BY `$orderCol` LIMIT $limit";
            case 3:
                /** @var string $groupCol */
                $groupCol = $this->faker->randomElement($columns);
                return "SELECT COUNT(*) AS cnt, `$groupCol` FROM `$table` GROUP BY `$groupCol`";
            case 4:
                /** @var string $col */
                $col = $this->faker->randomElement($columns);
                return "SELECT DISTINCT `$col` FROM `$table`";
            default:
                return "SELECT * FROM `$table`";
        }
    }

    public function buildInsert(SchemaDefinition $schema): string
    {
        $table = $schema->name;
        $columns = $schema->columns;
        $values = [];

        foreach ($columns as $col) {
            $values[] = $this->generateLiteral($col, $schema);
        }

        $colList = implode(', ', array_map(fn (string $c) => "`$c`", $columns));
        $valList = implode(', ', $values);

        return "INSERT INTO `$table` ($colList) VALUES ($valList)";
    }

    public function buildUpdate(SchemaDefinition $schema): string
    {
        $table = $schema->name;
        $columns = $schema->columns;
        $pks = $schema->primaryKeys;

        $nonPkCols = array_values(array_diff($columns, $pks));
        if ($nonPkCols === []) {
            $nonPkCols = $columns;
        }
        /** @var string $updateCol */
        $updateCol = $this->faker->randomElement($nonPkCols);
        $newValue = $this->generateLiteral($updateCol, $schema);

        $whereClause = $this->buildPkWhere($schema);

        return "UPDATE `$table` SET `$updateCol` = $newValue WHERE $whereClause";
    }

    public function buildDelete(SchemaDefinition $schema): string
    {
        $table = $schema->name;
        $whereClause = $this->buildPkWhere($schema);

        return "DELETE FROM `$table` WHERE $whereClause";
    }

    private function buildPkWhere(SchemaDefinition $schema): string
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
    private function randomColumns(array $columns): string
    {
        $count = $this->faker->numberBetween(1, count($columns));
        /** @var array<int, string> $selected */
        $selected = $this->faker->randomElements($columns, $count);
        return implode(', ', array_map(fn (string $c) => "`$c`", $selected));
    }

    private function generateLiteral(string $column, SchemaDefinition $schema): string
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
            /** @var string $choice */
            $choice = $this->faker->randomElement(['a', 'b', 'c']);
            return "'" . $choice . "'";
        }

        if (str_contains($col, 'set')) {
            /** @var array<int, string> $selected */
            $selected = $this->faker->randomElements(['x', 'y', 'z'], $this->faker->numberBetween(1, 3));
            return "'" . implode(',', $selected) . "'";
        }

        $str = $this->faker->lexify('????');
        return "'" . addslashes($str) . "'";
    }
}
