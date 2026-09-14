<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use Faker\Generator;

/**
 * Generates SQL operations that refer to the active fixture schema.
 */
final class SchemaAwareSqlBuilder
{
    private Generator $faker;

    /**
     * Bind the connection, schema generator and deterministic input dependencies.
     */
    public function __construct(Generator $faker)
    {
        $this->faker = $faker;
    }

    /**
     * Generate a SELECT over columns and predicates from this schema.
     */
    public function buildSelect(SchemaDefinition $schema): string
    {
        $table = $schema->name;
        $columns = $schema->columns;
        $variant = $this->faker->numberBetween(0, 6);

        switch ($variant) {
            case 0:
                return "SELECT * FROM `$table`";
            case 1:
                $cols = (new SqlExpressionGenerator($this->faker))->randomColumns($columns);

                $whereCol = (new SqlExpressionGenerator($this->faker))->chooseColumn($columns);
                $literal = (new SqlExpressionGenerator($this->faker))->generateLiteral($whereCol, $schema);
                return "SELECT $cols FROM `$table` WHERE `$whereCol` = $literal";
            case 2:
                $cols = (new SqlExpressionGenerator($this->faker))->randomColumns($columns);

                $orderCol = (new SqlExpressionGenerator($this->faker))->chooseColumn($columns);
                $limit = $this->faker->numberBetween(1, 10);
                return "SELECT $cols FROM `$table` ORDER BY `$orderCol` LIMIT $limit";
            case 3:

                $groupCol = (new SqlExpressionGenerator($this->faker))->chooseColumn($columns);
                return "SELECT COUNT(*) AS cnt, `$groupCol` FROM `$table` GROUP BY `$groupCol`";
            case 4:

                $col = (new SqlExpressionGenerator($this->faker))->chooseColumn($columns);
                return "SELECT DISTINCT `$col` FROM `$table`";
            case 5:
                $enumColumns = array_values(array_filter(
                    $columns,
                    static fn (string $column): bool => str_contains(strtolower($column), 'enum'),
                ));
                if ($enumColumns === []) {
                    return "SELECT * FROM `$table`";
                }

                $enumColumn = (new SqlExpressionGenerator($this->faker))->chooseColumn($enumColumns);
                return "SELECT `$enumColumn` FROM `$table` WHERE `$enumColumn` > 'a' ORDER BY `$enumColumn`";
            case 6:

                $derivedColumn = (new SqlExpressionGenerator($this->faker))->chooseColumn($columns);
                return "SELECT `$derivedColumn` FROM (SELECT `$derivedColumn` FROM `$table`) AS `_ztd_derived`";
            default:
                return "SELECT * FROM `$table`";
        }
    }

    /**
     * Generate an INSERT using schema-appropriate literal values.
     */
    public function buildInsert(SchemaDefinition $schema): string
    {
        $table = $schema->name;
        $columns = $schema->columns;
        $variant = $schema->defaultColumns === [] ? 0 : $this->faker->numberBetween(0, 2);
        if ($variant === 2 && count($schema->defaultColumns) === count($columns)) {
            return "INSERT INTO `$table` () VALUES ()";
        }
        if ($variant === 1) {
            $columns = array_values(array_diff($columns, $schema->defaultColumns));
        }
        $values = [];

        foreach ($columns as $col) {
            $values[] = $variant === 2 && in_array($col, $schema->defaultColumns, true)
                ? 'DEFAULT'
                : (new SqlExpressionGenerator($this->faker))->generateLiteral($col, $schema);
        }

        $colList = implode(', ', array_map(fn (string $c) => "`$c`", $columns));
        $valList = implode(', ', $values);

        if ($variant === 0 && $this->faker->boolean(25)) {
            return "INSERT INTO `$table` ($colList) SELECT $valList";
        }

        return "INSERT INTO `$table` ($colList) VALUES ($valList)";
    }

    /**
     * Generate an UPDATE limited by a primary-key predicate.
     */
    public function buildUpdate(SchemaDefinition $schema): string
    {
        $table = $schema->name;
        $columns = $schema->columns;
        $pks = $schema->primaryKeys;

        $nonPkCols = array_values(array_diff($columns, $pks));
        if ($nonPkCols === []) {
            $nonPkCols = $columns;
        }

        $updateCol = (new SqlExpressionGenerator($this->faker))->chooseColumn($nonPkCols);
        $newValue = (new SqlExpressionGenerator($this->faker))->isTextColumn($updateCol) && $this->faker->boolean(25)
            ? "''"
            : (new SqlExpressionGenerator($this->faker))->generateLiteral($updateCol, $schema);

        $whereClause = (new SqlExpressionGenerator($this->faker))->buildPkWhere($schema);

        return "UPDATE `$table` SET `$updateCol` = $newValue WHERE $whereClause";
    }

    /**
     * Generate a DELETE limited by a primary-key predicate.
     */
    public function buildDelete(SchemaDefinition $schema): string
    {
        $table = $schema->name;
        $whereClause = (new SqlExpressionGenerator($this->faker))->buildPkWhere($schema);

        return "DELETE FROM `$table` WHERE $whereClause";
    }








}
