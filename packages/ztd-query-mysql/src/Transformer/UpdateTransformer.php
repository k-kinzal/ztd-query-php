<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Components\Condition;
use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Components\Limit;
use PhpMyAdmin\SqlParser\Components\OrderKeyword;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Rewrite\SqlTransformer;

/**
 * Transforms UPDATE statements into SELECT projections with CTE shadowing.
 */
final class UpdateTransformer implements SqlTransformer
{
    private MySqlParser $parser;
    private SelectTransformer $selectTransformer;

    public function __construct(MySqlParser $parser, SelectTransformer $selectTransformer)
    {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
    }

    /**
     * {@inheritDoc}
     */
    public function transform(string $sql, array $tables): string
    {
        $statements = $this->parser->parse($sql);
        if (!isset($statements[0]) || !$statements[0] instanceof UpdateStatement) {
            throw new UnsupportedSqlException($sql, 'Expected UPDATE statement');
        }

        if (preg_match('/\bPARTITION\s*\(([^)]+)\)/i', $sql) === 1) {
            throw new UnsupportedSqlException($sql, 'PARTITION clause not supported');
        }

        $statement = $statements[0];

        if ($statement->tables === [] || !isset($statement->tables[0])) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve UPDATE target');
        }

        $targetExpr = $statement->tables[0];
        $targetTable = self::exprTable($targetExpr);
        if ($targetTable === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $columns = $tables[$targetTable]['columns'] ?? [];

        $projection = $this->buildProjection($statement, $columns);

        return $this->selectTransformer->transform($projection['sql'], $tables);
    }

    /**
     * Build a result-select SQL from an UPDATE statement.
     *
     * @param UpdateStatement $stmt
     * @param array<int, string> $columns
     * @return array{sql: string, table: string, tables: array<string, array{alias: string}>}
     */
    public function buildProjection(UpdateStatement $stmt, array $columns): array
    {
        if ($stmt->tables === null || $stmt->tables === []) {
            throw new \RuntimeException("Update statement has no tables?");
        }
        $targetTableExpr = $stmt->tables[0];
        $targetTableName = $targetTableExpr->table;
        if ($targetTableName === null || $targetTableName === '') {
            throw new \RuntimeException("Update statement target table name is empty.");
        }

        $qualifier = (($targetTableExpr->alias ?? '') !== '') ? $targetTableExpr->alias : $targetTableName;

        /** @var array<string, array{alias: string}> $allTargetTables */
        $allTargetTables = [];
        foreach ($stmt->tables as $tableExpr) {
            $tableName = self::exprTable($tableExpr) ?? '';
            $alias = $tableExpr->alias ?? $tableName;
            if ($tableName !== '') {
                $allTargetTables[$tableName] = ['alias' => $alias];
            }
        }

        $selectCols = [];
        $coveredCols = [];

        if ($stmt->set !== null && $stmt->set !== []) {
            foreach ($stmt->set as $setOp) {
                $colName = $setOp->column;
                $colName = trim($colName, '`"\'');
                if (str_contains($colName, '.')) {
                    $parts = explode('.', $colName);
                    $colName = trim(end($parts), '`"\'');
                }

                $selectCols[] = $setOp->value . " AS `" . $colName . "`";
                $coveredCols[$colName] = true;
            }
        }

        foreach ($columns as $col) {
            if (!isset($coveredCols[$col])) {
                $selectCols[] = "`$qualifier`.`$col`";
            }
        }

        if ($selectCols === []) {
            $selectCols[] = "*";
        }
        $selectList = implode(", ", $selectCols);

        $aliasClause = "";
        if (($targetTableExpr->alias ?? '') !== '') {
            $aliasClause = " AS " . $targetTableExpr->alias;
        }

        $additionalTables = $this->buildAdditionalTables($stmt);

        $joinClause = $this->buildJoinClause($stmt);

        $whereClause = "";
        if ($stmt->where !== null && $stmt->where !== []) {
            $whereClause = " WHERE " . Condition::build($stmt->where);
        }

        $orderByClause = "";
        if ($stmt->order !== null && $stmt->order !== []) {
            $orderParts = [];
            foreach ($stmt->order as $orderExpr) {
                $orderParts[] = OrderKeyword::build($orderExpr);
            }
            $orderByClause = " ORDER BY " . implode(", ", $orderParts);
        }

        $limitClause = "";
        if ($stmt->limit !== null) {
            $limitClause = " LIMIT " . Limit::build($stmt->limit);
        }

        $sql = "SELECT $selectList FROM `$targetTableName`$aliasClause$additionalTables$joinClause$whereClause$orderByClause$limitClause";

        return ['sql' => $sql, 'table' => $targetTableName, 'tables' => $allTargetTables];
    }

    private function buildAdditionalTables(UpdateStatement $stmt): string
    {
        if ($stmt->tables === null || count($stmt->tables) <= 1) {
            return '';
        }

        $parts = [];
        $tableCount = count($stmt->tables);
        for ($i = 1; $i < $tableCount; $i++) {
            $tableExpr = $stmt->tables[$i];
            $tableName = self::exprTable($tableExpr) ?? '';
            $alias = $tableExpr->alias ?? '';

            $part = "`$tableName`";
            if ($alias !== '' && $alias !== $tableName) {
                $part .= " AS $alias";
            }
            $parts[] = $part;
        }

        return ', ' . implode(', ', $parts);
    }

    private function buildJoinClause(UpdateStatement $stmt): string
    {
        if ($stmt->join === null || $stmt->join === []) {
            return '';
        }

        $joinParts = [];
        foreach ($stmt->join as $join) {
            $joinType = $join->type !== '' ? $join->type : 'JOIN';
            if (!str_contains(strtoupper($joinType), 'JOIN')) {
                $joinType .= ' JOIN';
            }
            $joinTable = $join->expr !== null ? (self::exprTable($join->expr) ?? '') : '';
            $joinAlias = $join->expr !== null ? ($join->expr->alias ?? '') : '';

            $joinStr = " $joinType `$joinTable`";
            if ($joinAlias !== '') {
                $joinStr .= " AS $joinAlias";
            }

            if ($join->on !== null && $join->on !== []) {
                $onParts = [];
                foreach ($join->on as $condition) {
                    $onParts[] = $condition->expr !== '' ? $condition->expr : Condition::build([$condition]);
                }
                $joinStr .= ' ON ' . implode(' ', $onParts);
            }

            if ($join->using !== null) {
                $usingValues = $join->using->values;
                if ($usingValues !== []) {
                    $joinStr .= ' USING (' . implode(', ', $usingValues) . ')';
                }
            }

            $joinParts[] = $joinStr;
        }

        return implode('', $joinParts);
    }

    /**
     * Resolve table name from an Expression, preferring ->table over ->expr.
     */
    private static function exprTable(Expression $expr): ?string
    {
        return (($expr->table ?? '') !== '') ? $expr->table : ($expr->expr ?? null);
    }
}
