<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Components\Condition;
use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Components\JoinKeyword;
use PhpMyAdmin\SqlParser\Components\Limit;
use PhpMyAdmin\SqlParser\Components\OrderKeyword;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Rewrite\SqlTransformer;

/**
 * Transforms DELETE statements into SELECT projections with CTE shadowing.
 */
final class DeleteTransformer implements SqlTransformer
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
        if (!isset($statements[0]) || !$statements[0] instanceof DeleteStatement) {
            throw new UnsupportedSqlException($sql, 'Expected DELETE statement');
        }

        $statement = $statements[0];

        $targetTable = null;
        if ($statement->from !== null && $statement->from !== []) {
            $targetExpr = $statement->from[0];
            $targetTable = self::exprTable($targetExpr);
        }

        $columnNames = [];
        if ($targetTable !== null && isset($tables[$targetTable])) {
            $columnNames = $tables[$targetTable]['columns'];
        }

        $projection = $this->buildProjection($statement, $sql, $columnNames);

        return $this->selectTransformer->transform($projection['sql'], $tables);
    }

    /**
     * Build a result-select SQL and resolve the target table(s).
     *
     * @param DeleteStatement $stmt
     * @param string $originalSql
     * @param array<int, string> $columns
     * @return array{sql: string, table: string, tables: array<string, array{alias: string}>}
     */
    public function buildProjection(DeleteStatement $stmt, string $originalSql, array $columns): array
    {
        $targetTableName = 'unknown';
        $targetTableAlias = null;
        /** @var array<string, array{alias: string}> $allTargetTables */
        $allTargetTables = [];

        if ($stmt->columns !== null && $stmt->columns !== []) {
            $targetExpr = $stmt->columns[0];
            $targetTableAlias = self::exprTable($targetExpr);

            foreach ($stmt->columns as $colExpr) {
                $alias = self::exprTable($colExpr);
                if ($alias !== null && $alias !== '') {
                    $allTargetTables[$alias] = ['alias' => $alias];
                }
            }
        }

        if ($targetTableAlias === null || $targetTableAlias === '') {
            if ($stmt->from !== null && $stmt->from !== []) {
                $targetTableExpr = $stmt->from[0];
                $targetTableName = self::exprTable($targetTableExpr);
                if ($targetTableName === null || $targetTableName === '') {
                    throw new \RuntimeException('Delete target table could not be resolved.');
                }
                $targetTableAlias = self::exprAlias($targetTableExpr) ?? $targetTableName;
            }
        } else {
            $found = false;
            if ($stmt->from !== null && $stmt->from !== []) {
                foreach ($stmt->from as $from) {
                    $alias = self::exprAlias($from);
                    if ($alias === $targetTableAlias) {
                        $targetTableName = self::exprTable($from);
                        if ($targetTableName !== null && $targetTableName !== '') {
                            $found = true;
                        }
                        break;
                    }
                }
            }
            if (!$found && $stmt->join !== null && $stmt->join !== []) {
                foreach ($stmt->join as $join) {
                    if ($join->expr === null) {
                        continue;
                    }
                    $alias = self::exprAlias($join->expr);
                    if ($alias === $targetTableAlias) {
                        $targetTableName = self::exprTable($join->expr);
                        if ($targetTableName !== null && $targetTableName !== '') {
                            $found = true;
                        }
                        break;
                    }
                }
            }
            if (!$found && $stmt->using !== null && $stmt->using !== []) {
                foreach ($stmt->using as $using) {
                    $alias = self::exprAlias($using);
                    if ($alias === $targetTableAlias) {
                        $targetTableName = self::exprTable($using);
                        if ($targetTableName !== null && $targetTableName !== '') {
                            $found = true;
                        }
                        break;
                    }
                }
            }
        }

        if (preg_match('/\bPARTITION\s*\(([^)]+)\)/i', $originalSql, $matches) === 1) {
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
        if ($stmt->where !== null && $stmt->where !== []) {
            $whereClause = " WHERE " . Condition::build($stmt->where);
        }

        $orderClause = "";
        if ($stmt->order !== null && $stmt->order !== []) {
            $orderParts = [];
            foreach ($stmt->order as $order) {
                $orderParts[] = OrderKeyword::build($order);
            }
            $orderClause = " ORDER BY " . implode(", ", $orderParts);
        }

        $limitClause = "";
        if ($stmt->limit !== null) {
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
            $resolvedTables[$targetTableName] = ['alias' => $targetTableAlias];
        }

        return ['sql' => $sql, 'table' => $targetTableName, 'tables' => $resolvedTables];
    }

    private function resolveAliasToTable(string $alias, DeleteStatement $stmt): ?string
    {
        if ($stmt->from !== null && $stmt->from !== []) {
            foreach ($stmt->from as $from) {
                $fromAlias = self::exprAlias($from);
                if ($fromAlias === $alias) {
                    return self::exprTable($from);
                }
            }
        }

        if ($stmt->join !== null && $stmt->join !== []) {
            foreach ($stmt->join as $join) {
                if ($join->expr === null) {
                    continue;
                }
                $joinAlias = self::exprAlias($join->expr);
                if ($joinAlias === $alias) {
                    return self::exprTable($join->expr);
                }
            }
        }

        if ($stmt->using !== null && $stmt->using !== []) {
            foreach ($stmt->using as $using) {
                $usingAlias = self::exprAlias($using);
                if ($usingAlias === $alias) {
                    return self::exprTable($using);
                }
            }
        }

        return $alias;
    }

    /**
     * Resolve table name from an Expression, preferring ->table over ->expr.
     */
    private static function exprTable(Expression $expr): ?string
    {
        return (($expr->table ?? '') !== '') ? $expr->table : $expr->expr;
    }

    /**
     * Resolve alias from an Expression, falling back to table name.
     */
    private static function exprAlias(Expression $expr): ?string
    {
        return (($expr->alias ?? '') !== '') ? $expr->alias : self::exprTable($expr);
    }
}
