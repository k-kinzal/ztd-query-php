<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Components\Condition;
use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Components\JoinKeyword;
use PhpMyAdmin\SqlParser\Components\Limit;
use PhpMyAdmin\SqlParser\Components\OrderKeyword;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;

/**
 * Builds SELECT projections for DELETE statements.
 */
class DeleteTransformer
{
    /**
     * Build a result-select SQL and resolve the target table(s).
     *
     * @param DeleteStatement $stmt
     * @param string $originalSql
     * @param array<int, string> $columns
     * @return array{sql: string, table: string, tables: array<string, array{alias: string}>}
     */
    public function build(DeleteStatement $stmt, string $originalSql, array $columns): array
    {
        $targetTableName = 'unknown';
        $targetTableAlias = null;
        /** @var array<string, array{alias: string}> $allTargetTables */
        $allTargetTables = [];

        if ($stmt->columns !== null && $stmt->columns !== []) {
            // For multi-table DELETE, collect all target tables
            $targetExpr = $stmt->columns[0];
            $targetTableAlias = $targetExpr->table ?: $targetExpr->expr;

            // Collect all target table aliases/names for multi-table DELETE
            foreach ($stmt->columns as $colExpr) {
                $alias = $colExpr->table ?: $colExpr->expr;
                if ($alias !== null && $alias !== '') {
                    $allTargetTables[$alias] = ['alias' => $alias];
                }
            }
        }

        if (empty($targetTableAlias)) {
            if ($stmt->from !== null && $stmt->from !== []) {
                $targetTableExpr = $stmt->from[0];
                $targetTableName = $targetTableExpr->table ?: $targetTableExpr->expr;
                if ($targetTableName === null || $targetTableName === '') {
                    throw new \RuntimeException('Delete target table could not be resolved.');
                }
                $targetTableAlias = !empty($targetTableExpr->alias) ? $targetTableExpr->alias : $targetTableName;
            }
        } else {
            $found = false;
            if (!empty($stmt->from)) {
                foreach ($stmt->from as $from) {
                    $alias = $from->alias ?: ($from->table ?: $from->expr);
                    if ($alias === $targetTableAlias) {
                        $targetTableName = $from->table ?: $from->expr;
                        if ($targetTableName !== null && $targetTableName !== '') {
                            $found = true;
                        }
                        break;
                    }
                }
            }
            if (!$found && !empty($stmt->join)) {
                foreach ($stmt->join as $join) {
                    if ($join->expr === null) {
                        continue;
                    }
                    $alias = $join->expr->alias ?: ($join->expr->table ?: $join->expr->expr);
                    if ($alias === $targetTableAlias) {
                        $targetTableName = $join->expr->table ?: $join->expr->expr;
                        if ($targetTableName !== null && $targetTableName !== '') {
                            $found = true;
                        }
                        break;
                    }
                }
            }
            if (!$found && !empty($stmt->using)) {
                foreach ($stmt->using as $using) {
                    $alias = $using->alias ?: ($using->table ?: $using->expr);
                    if ($alias === $targetTableAlias) {
                        $targetTableName = $using->table ?: $using->expr;
                        if ($targetTableName !== null && $targetTableName !== '') {
                            $found = true;
                        }
                        break;
                    }
                }
            }
        }

        if (preg_match('/\bPARTITION\s*\(([^)]+)\)/i', $originalSql, $matches)) {
            throw new \RuntimeException("ZTD Write Protection: PARTITION clause in DELETE is not supported (cannot simulate safely).");
        }

        $fromClause = "";
        if ($stmt->from !== null && $stmt->from !== []) {
            $fromParts = [];
            foreach ($stmt->from as $expr) {
                $fromParts[] = Expression::build($expr);
            }
            $fromClause = ' FROM ' . implode(', ', $fromParts);
        }

        $joinClause = "";
        if ($stmt->join !== null && $stmt->join !== []) {
            $joinClause = ' ' . JoinKeyword::build($stmt->join);
        }

        $usingClause = "";
        if ($stmt->using !== null && $stmt->using !== []) {
            $usingParts = [];
            foreach ($stmt->using as $expr) {
                $usingParts[] = Expression::build($expr);
            }
            $fromClause = ' FROM ' . implode(', ', $usingParts);
        }

        $whereClause = "";
        if (!empty($stmt->where)) {
            $whereClause = " WHERE " . Condition::build($stmt->where);
        }

        $orderClause = "";
        if (!empty($stmt->order)) {
            $orderParts = [];
            foreach ($stmt->order as $order) {
                $orderParts[] = OrderKeyword::build($order);
            }
            $orderClause = " ORDER BY " . implode(", ", $orderParts);
        }

        $limitClause = "";
        if (!empty($stmt->limit)) {
            $limitClause = " LIMIT " . Limit::build($stmt->limit);
        }

        $targetTableAlias = $targetTableAlias ?? $targetTableName;
        if ($targetTableAlias === null || $targetTableAlias === '') {
            throw new \RuntimeException('Delete target table could not be resolved.');
        }

        $selectList = "`$targetTableAlias`.*";
        if ($columns !== []) {
            $parts = [];
            foreach ($columns as $column) {
                $parts[] = "`$targetTableAlias`.`$column` AS `$column`";
            }
            $selectList = implode(', ', $parts);
        }

        $sql = "SELECT $selectList$fromClause$joinClause$usingClause $whereClause$orderClause$limitClause";

        if ($targetTableName === null || $targetTableName === '') {
            throw new \RuntimeException('Delete target table could not be resolved.');
        }

        // Build the resolved tables map (alias => actual table name)
        /** @var array<string, array{alias: string}> $resolvedTables */
        $resolvedTables = [];
        if ($allTargetTables !== []) {
            foreach ($allTargetTables as $alias => $info) {
                $resolvedName = $this->resolveAliasToTable($alias, $stmt);
                if ($resolvedName !== null) {
                    $resolvedTables[$resolvedName] = ['alias' => $alias];
                }
            }
        } else {
            // Single table delete
            $resolvedTables[$targetTableName] = ['alias' => $targetTableAlias];
        }

        return ['sql' => $sql, 'table' => $targetTableName, 'tables' => $resolvedTables];
    }

    /**
     * Resolve a table alias to its actual table name.
     *
     * @param string $alias The alias or table name to resolve.
     * @param DeleteStatement $stmt The DELETE statement.
     * @return string|null The resolved table name or null if not found.
     */
    private function resolveAliasToTable(string $alias, DeleteStatement $stmt): ?string
    {
        // Check FROM clause
        if (!empty($stmt->from)) {
            foreach ($stmt->from as $from) {
                $fromAlias = $from->alias ?: ($from->table ?: $from->expr);
                if ($fromAlias === $alias) {
                    return $from->table ?: $from->expr;
                }
            }
        }

        // Check JOIN clause
        if (!empty($stmt->join)) {
            foreach ($stmt->join as $join) {
                if ($join->expr === null) {
                    continue;
                }
                $joinAlias = $join->expr->alias ?: ($join->expr->table ?: $join->expr->expr);
                if ($joinAlias === $alias) {
                    return $join->expr->table ?: $join->expr->expr;
                }
            }
        }

        // Check USING clause
        if (!empty($stmt->using)) {
            foreach ($stmt->using as $using) {
                $usingAlias = $using->alias ?: ($using->table ?: $using->expr);
                if ($usingAlias === $alias) {
                    return $using->table ?: $using->expr;
                }
            }
        }

        // The alias might be the table name itself
        return $alias;
    }
}
