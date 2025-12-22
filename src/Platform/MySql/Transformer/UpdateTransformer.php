<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Components\Condition;
use PhpMyAdmin\SqlParser\Components\Limit;
use PhpMyAdmin\SqlParser\Components\OrderKeyword;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;

/**
 * Builds SELECT projections for UPDATE statements.
 */
class UpdateTransformer
{
    /**
     * Build a result-select SQL from an UPDATE statement.
     *
     * @param UpdateStatement $stmt
     * @param array<int, string> $columns
     * @return array{sql: string, table: string, tables: array<string, array{alias: string}>}
     */
    public function build(UpdateStatement $stmt, array $columns): array
    {
        if (empty($stmt->tables)) {
            throw new \RuntimeException("Update statement has no tables?");
        }
        $targetTableExpr = $stmt->tables[0];
        $targetTableName = $targetTableExpr->table;
        if ($targetTableName === null || $targetTableName === '') {
            throw new \RuntimeException("Update statement target table name is empty.");
        }

        $qualifier = !empty($targetTableExpr->alias) ? $targetTableExpr->alias : $targetTableName;

        // Collect all target tables (for multi-table UPDATE)
        /** @var array<string, array{alias: string}> $allTargetTables */
        $allTargetTables = [];
        foreach ($stmt->tables as $tableExpr) {
            $tableName = $tableExpr->table ?? $tableExpr->expr ?? '';
            $alias = $tableExpr->alias ?? $tableName;
            if ($tableName !== '') {
                $allTargetTables[$tableName] = ['alias' => $alias];
            }
        }

        $selectCols = [];
        $coveredCols = [];

        if ($stmt->set) {
            foreach ($stmt->set as $setOp) {
                $colName = $setOp->column;
                // Strip backticks and quotes from column name
                $colName = trim($colName, '`"\'');
                // Handle qualified names like `table`.`column` or table.column
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

        if (empty($selectCols)) {
            $selectCols[] = "*";
        }
        $selectList = implode(", ", $selectCols);

        $aliasClause = "";
        if (!empty($targetTableExpr->alias)) {
            $aliasClause = " AS " . $targetTableExpr->alias;
        }

        // Build additional tables for multi-table UPDATE (UPDATE t1, t2 SET ...)
        $additionalTables = $this->buildAdditionalTables($stmt);

        // Build JOIN clauses
        $joinClause = $this->buildJoinClause($stmt);

        $whereClause = "";
        if (!empty($stmt->where)) {
            $whereClause = " WHERE " . Condition::build($stmt->where);
        }

        // Build ORDER BY clause
        $orderByClause = "";
        if (!empty($stmt->order)) {
            $orderParts = [];
            foreach ($stmt->order as $orderExpr) {
                $orderParts[] = OrderKeyword::build($orderExpr);
            }
            $orderByClause = " ORDER BY " . implode(", ", $orderParts);
        }

        // Build LIMIT clause
        $limitClause = "";
        if (!empty($stmt->limit)) {
            $limitClause = " LIMIT " . Limit::build($stmt->limit);
        }

        $sql = "SELECT $selectList FROM `$targetTableName`$aliasClause$additionalTables$joinClause$whereClause$orderByClause$limitClause";

        return ['sql' => $sql, 'table' => $targetTableName, 'tables' => $allTargetTables];
    }

    /**
     * Build additional table references for multi-table UPDATE.
     *
     * @param UpdateStatement $stmt
     * @return string
     */
    private function buildAdditionalTables(UpdateStatement $stmt): string
    {
        if ($stmt->tables === null || count($stmt->tables) <= 1) {
            return '';
        }

        $parts = [];
        // Skip the first table (already in FROM clause)
        $tableCount = count($stmt->tables);
        for ($i = 1; $i < $tableCount; $i++) {
            $tableExpr = $stmt->tables[$i];
            $tableName = $tableExpr->table ?? $tableExpr->expr ?? '';
            $alias = $tableExpr->alias ?? '';

            $part = "`$tableName`";
            if ($alias !== '' && $alias !== $tableName) {
                $part .= " AS $alias";
            }
            $parts[] = $part;
        }

        return ', ' . implode(', ', $parts);
    }

    /**
     * Build JOIN clause from UPDATE statement.
     *
     * @param UpdateStatement $stmt
     * @return string
     */
    private function buildJoinClause(UpdateStatement $stmt): string
    {
        if ($stmt->join === null || $stmt->join === []) {
            return '';
        }

        $joinParts = [];
        foreach ($stmt->join as $join) {
            $joinType = $join->type !== '' ? $join->type : 'JOIN';
            // Ensure JOIN keyword is present (e.g., "LEFT" becomes "LEFT JOIN")
            if (!str_contains(strtoupper($joinType), 'JOIN')) {
                $joinType .= ' JOIN';
            }
            $joinTable = $join->expr !== null ? ($join->expr->table !== '' ? $join->expr->table : ($join->expr->expr ?? '')) : '';
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
}
