<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Sqlite;

use Faker\Generator;
use Fuzz\Correctness\SchemaDefinition;

final class SqliteSchemaAwareSqlBuilder
{
    private Generator $faker;

    public function __construct(Generator $faker)
    {
        $this->faker = $faker;
    }

    public function buildSelect(SchemaDefinition $schema): string
    {
        $table = $this->quoteIdentifier($schema->name);
        $columns = $schema->columns;
        $variant = $this->faker->numberBetween(0, 4);

        switch ($variant) {
            case 0:
                return "SELECT * FROM $table";
            case 1:
                $cols = $this->randomColumns($columns);
                /** @var string $whereCol */
                $whereCol = $this->faker->randomElement($columns);
                $literal = $this->generateLiteral($whereCol);
                return "SELECT $cols FROM $table WHERE " . $this->quoteIdentifier($whereCol) . " = $literal";
            case 2:
                $cols = $this->randomColumns($columns);
                /** @var string $orderCol */
                $orderCol = $this->faker->randomElement($columns);
                $limit = $this->faker->numberBetween(1, 10);
                return "SELECT $cols FROM $table ORDER BY " . $this->quoteIdentifier($orderCol) . " LIMIT $limit";
            case 3:
                /** @var string $groupCol */
                $groupCol = $this->faker->randomElement($columns);
                return "SELECT COUNT(*) AS cnt, " . $this->quoteIdentifier($groupCol) . " FROM $table GROUP BY " . $this->quoteIdentifier($groupCol);
            case 4:
                /** @var string $col */
                $col = $this->faker->randomElement($columns);
                return "SELECT DISTINCT " . $this->quoteIdentifier($col) . " FROM $table";
            default:
                return "SELECT * FROM $table";
        }
    }

    public function buildInsert(SchemaDefinition $schema): string
    {
        $table = $this->quoteIdentifier($schema->name);
        $columns = $schema->columns;
        $values = [];

        foreach ($columns as $col) {
            $values[] = $this->generateLiteral($col);
        }

        $colList = implode(', ', array_map(fn ($c) => $this->quoteIdentifier($c), $columns));
        $valList = implode(', ', $values);

        return "INSERT INTO $table ($colList) VALUES ($valList)";
    }

    public function buildUpdate(SchemaDefinition $schema): string
    {
        $table = $this->quoteIdentifier($schema->name);
        $columns = $schema->columns;
        $pks = $schema->primaryKeys;

        $nonPkCols = array_values(array_diff($columns, $pks));
        if ($nonPkCols === []) {
            $nonPkCols = $columns;
        }
        /** @var string $updateCol */
        $updateCol = $this->faker->randomElement($nonPkCols);
        $newValue = $this->generateLiteral($updateCol);

        $whereClause = $this->buildPkWhere($schema);

        return "UPDATE $table SET " . $this->quoteIdentifier($updateCol) . " = $newValue WHERE $whereClause";
    }

    public function buildDelete(SchemaDefinition $schema): string
    {
        $table = $this->quoteIdentifier($schema->name);
        $whereClause = $this->buildPkWhere($schema);

        return "DELETE FROM $table WHERE $whereClause";
    }

    private function buildPkWhere(SchemaDefinition $schema): string
    {
        $conditions = [];
        foreach ($schema->primaryKeys as $pk) {
            $literal = $this->generateLiteral($pk);
            $conditions[] = $this->quoteIdentifier($pk) . " = $literal";
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
        return implode(', ', array_map(fn ($c) => $this->quoteIdentifier($c), $selected));
    }

    private function generateLiteral(string $column): string
    {
        $col = strtolower($column);

        if (str_contains($col, 'id') || str_contains($col, 'quantity') || str_contains($col, 'int') || str_contains($col, 'numeric')) {
            return (string) $this->faker->numberBetween(1, 100);
        }

        if (str_contains($col, 'real') || str_contains($col, 'float') || str_contains($col, 'double')) {
            return (string) round($this->faker->randomFloat(4, -1000, 1000), 4);
        }

        $str = $this->faker->lexify('????');
        return "'" . str_replace("'", "''", $str) . "'";
    }

    private function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
