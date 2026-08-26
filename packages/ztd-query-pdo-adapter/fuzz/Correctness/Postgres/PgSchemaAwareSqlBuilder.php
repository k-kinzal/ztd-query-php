<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Postgres;

use Faker\Generator;
use Fuzz\Correctness\SchemaDefinition;

final class PgSchemaAwareSqlBuilder
{
    private Generator $faker;

    /**
     * Binds the instance to what it will work from.
     *
     * @param Generator $faker
     */
    public function __construct(Generator $faker)
    {
        $this->faker = $faker;
    }

    /**
     * Builds select.
     *
     * @param SchemaDefinition $schema
     * @return string
     */
    public function buildSelect(SchemaDefinition $schema): string
    {
        $table = $this->quoteIdentifier($schema->name);
        $columns = $schema->columns;
        $variant = $this->faker->numberBetween(0, 5);

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
                return 'SELECT COUNT(*) AS cnt, ' . $this->quoteIdentifier($groupCol) . " FROM $table GROUP BY " . $this->quoteIdentifier($groupCol);
            case 4:
                /** @var string $col */
                $col = $this->faker->randomElement($columns);
                return 'SELECT DISTINCT ' . $this->quoteIdentifier($col) . " FROM $table";
            case 5:
                /** @var string $derivedColumn */
                $derivedColumn = $this->faker->randomElement($columns);
                $quotedColumn = $this->quoteIdentifier($derivedColumn);
                return "SELECT $quotedColumn FROM (SELECT $quotedColumn FROM $table) AS \"_ztd_derived\"";
            default:
                return "SELECT * FROM $table";
        }
    }

    /**
     * Builds join select.
     *
     * @param SchemaDefinition $left
     * @param SchemaDefinition $right
     * @return string
     */
    public function buildJoinSelect(SchemaDefinition $left, SchemaDefinition $right): string
    {
        $leftTable = $this->quoteIdentifier($left->name);
        $rightTable = $this->quoteIdentifier($right->name);
        $leftKey = $left->primaryKeys[0] ?? $left->columns[0];
        $rightKey = $right->columns[0];
        /** @var string $leftColumn */
        $leftColumn = $this->faker->randomElement($left->columns);
        /** @var string $rightColumn */
        $rightColumn = $this->faker->randomElement($right->columns);
        /** @var string $join */
        $join = $this->faker->randomElement([
            'JOIN',
            'INNER JOIN',
            'LEFT JOIN',
            'RIGHT JOIN',
            'FULL OUTER JOIN',
        ]);

        return 'SELECT l.' . $this->quoteIdentifier($leftColumn) . ' AS "left_value", '
            . 'r.' . $this->quoteIdentifier($rightColumn) . ' AS "right_value" '
            . "FROM $leftTable AS l $join $rightTable AS r "
            . 'ON l.' . $this->quoteIdentifier($leftKey) . ' = r.' . $this->quoteIdentifier($rightKey) . ' '
            . 'ORDER BY l.' . $this->quoteIdentifier($leftKey) . ' NULLS LAST, '
            . 'r.' . $this->quoteIdentifier($rightKey) . ' NULLS LAST, '
            . '"left_value" NULLS LAST, "right_value" NULLS LAST';
    }

    /**
     * Builds insert.
     *
     * @param SchemaDefinition $schema
     * @return string
     */
    public function buildInsert(SchemaDefinition $schema): string
    {
        $table = $this->quoteIdentifier($schema->name);
        $columns = array_filter($schema->columns, fn ($c) => $c !== 'id');
        $columns = array_values($columns);
        if ($columns === []) {
            $columns = $schema->columns;
        }
        $variant = $schema->defaultColumns === [] ? 0 : $this->faker->numberBetween(0, 2);
        if ($variant === 2 && count($schema->defaultColumns) === count($schema->columns)) {
            return "INSERT INTO $table DEFAULT VALUES";
        }
        if ($variant === 1) {
            $columns = array_values(array_diff($columns, $schema->defaultColumns));
            if ($columns === []) {
                return "INSERT INTO $table DEFAULT VALUES";
            }
        }
        $values = [];

        foreach ($columns as $col) {
            $values[] = $variant === 2 && in_array($col, $schema->defaultColumns, true)
                ? 'DEFAULT'
                : $this->generateLiteral($col);
        }

        $colList = implode(', ', array_map(fn ($c) => $this->quoteIdentifier($c), $columns));
        $valList = implode(', ', $values);

        if ($variant === 0 && $this->faker->boolean(25)) {
            return "INSERT INTO $table ($colList) SELECT $valList";
        }

        return "INSERT INTO $table ($colList) VALUES ($valList)";
    }

    /**
     * Builds update.
     *
     * @param SchemaDefinition $schema
     * @return string
     */
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
        if ($this->isTextColumn($updateCol) && $this->faker->boolean(25)) {
            $newValue = "''";
        } elseif ($this->isTextColumn($updateCol) && $this->faker->boolean(35)) {
            $column = $this->quoteIdentifier($updateCol);
            /** @var string $newValue */
            $newValue = $this->faker->randomElement([
                "TRIM(BOTH 'x' FROM $column)",
                "SUBSTRING($column FROM 1 FOR 3)",
            ]);
        }

        $whereClause = $this->faker->boolean(25)
            ? $this->buildGroupedSubqueryWhere($schema)
            : $this->buildPkWhere($schema);

        return "UPDATE $table SET " . $this->quoteIdentifier($updateCol) . " = $newValue WHERE $whereClause";
    }

    /**
     * Builds delete.
     *
     * @param SchemaDefinition $schema
     * @return string
     */
    public function buildDelete(SchemaDefinition $schema): string
    {
        $table = $this->quoteIdentifier($schema->name);
        $whereClause = $this->faker->boolean(25)
            ? $this->buildGroupedSubqueryWhere($schema)
            : $this->buildPkWhere($schema);

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

    private function buildGroupedSubqueryWhere(SchemaDefinition $schema): string
    {
        $table = $this->quoteIdentifier($schema->name);
        $key = $this->quoteIdentifier($schema->primaryKeys[0] ?? $schema->columns[0]);

        return "$key IN (SELECT $key FROM $table GROUP BY $key HAVING COUNT(*) > 1)";
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

        if (str_contains($col, 'bit')) {
            return "B'10101010'";
        }

        if (str_contains($col, 'id') || str_contains($col, 'quantity') || str_contains($col, 'int') || str_contains($col, 'bigint') || str_contains($col, 'smallint')) {
            return (string) $this->faker->numberBetween(1, 100);
        }

        if (str_contains($col, 'real') || str_contains($col, 'float') || str_contains($col, 'double')) {
            return (string) round($this->faker->randomFloat(4, -1000, 1000), 4);
        }

        if (str_contains($col, 'numeric') || str_contains($col, 'decimal')) {
            return (string) round($this->faker->randomFloat(2, 0, 9999), 2);
        }

        if (str_contains($col, 'bool')) {
            return $this->faker->boolean() ? 'TRUE' : 'FALSE';
        }

        $str = $this->faker->lexify('????');
        return "'" . str_replace("'", "''", $str) . "'";
    }

    private function isTextColumn(string $column): bool
    {
        $column = strtolower($column);

        return str_contains($column, 'name')
            || str_contains($column, 'email')
            || str_contains($column, 'status')
            || str_contains($column, 'text')
            || str_contains($column, 'varchar')
            || str_contains($column, 'char');
    }

    private function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
