<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer\Delete;

use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Components\JoinKeyword;
use PhpMyAdmin\SqlParser\Components\Limit;
use PhpMyAdmin\SqlParser\Components\OrderKeyword;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use RuntimeException;
use ZtdQuery\Platform\MySql\DmlWhereClauseExtractor;
use ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames;
use ZtdQuery\Shadow\Mutation\MultiTableMutationTarget;

/**
 * Builds DELETE result projections from resolved targets and parsed query clauses.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ResultSelect
{
    /**
     * @param array<int, string> $columns
     * @param list<MultiTableMutationTarget> $targets
     * @return array{sql: string, table: string, tables: array<string, array{alias: string}>}
     * @throws RuntimeException
     */
    public function buildProjection(DeleteStatement $stmt, string $originalSql, array $columns, array $targets = []): array
    {
        $target = $this->primaryTarget($stmt);
        if (preg_match('/\bPARTITION\s*\(([^)]+)\)/i', $originalSql) === 1) {
            throw new RuntimeException('ZTD Write Protection: PARTITION clause in DELETE is not supported (cannot simulate safely).');
        }
        $resolved = $this->resolvedTables($stmt, $target);
        $selectList = $targets === []
            ? $this->selectList($target['alias'], $columns)
            : (new TargetProjection())->multiTableSelectList($resolved, $targets);
        $sql = 'SELECT ' . $selectList . $this->sourceClause($stmt) . $this->suffix($stmt, $originalSql);
        return ['sql' => $sql, 'table' => $target['table'], 'tables' => $resolved];
    }

    /**
     * @return array{table: string, alias: string, aliases: array<string, string>}
     * @throws RuntimeException
     */
    public function primaryTarget(DeleteStatement $stmt): array
    {
        $table = 'unknown';
        $first = $stmt->columns[0] ?? null;
        $alias = $first !== null ? ExpressionNames::table($first) : null;
        $aliases = [];
        foreach ($stmt->columns ?? [] as $column) {
            $name = ExpressionNames::table($column);
            if ($name !== null && $name !== '') {
                $aliases[$name] = $name;
            }
        }
        if ($alias === null || $alias === '') {
            $from = $stmt->from[0] ?? null;
            if ($from !== null) {
                $table = ExpressionNames::table($from);
                if ($table === null || $table === '') {
                    throw new RuntimeException('Delete target table could not be resolved.');
                }
                $alias = ExpressionNames::alias($from) ?? $table;
            }
        } else {
            $table = $this->namedTarget($stmt, $alias);
        }
        $alias ??= $table;
        if ($alias === null || $alias === '' || $table === null || $table === '') {
            throw new RuntimeException('Delete target table could not be resolved.');
        }
        return ['table' => $table, 'alias' => $alias, 'aliases' => $aliases];
    }

    /**
     * Resolve aliases in FROM, JOIN and USING precedence, skipping empty matches.
     */
    public function namedTarget(DeleteStatement $stmt, string $alias): ?string
    {
        $joins = [];
        foreach ($stmt->join ?? [] as $join) {
            if ($join->expr !== null) {
                $joins[] = $join->expr;
            }
        }
        $table = 'unknown';
        foreach ([$stmt->from ?? [], $joins, $stmt->using ?? []] as $expressions) {
            foreach ($expressions as $expression) {
                if (ExpressionNames::alias($expression) !== $alias) {
                    continue;
                }
                $table = ExpressionNames::table($expression);
                if ($table !== null && $table !== '') {
                    return $table;
                }
                break;
            }
        }
        return $table;
    }

    /**
     * USING replaces the FROM list while the parsed JOIN clauses remain attached.
     */
    public function sourceClause(DeleteStatement $stmt): string
    {
        $expressions = $stmt->using !== null && $stmt->using !== [] ? $stmt->using : ($stmt->from ?? []);
        $parts = [];
        foreach ($expressions as $expression) {
            $parts[] = Expression::build($expression);
        }
        $from = $parts === [] ? '' : ' FROM ' . implode(', ', $parts);
        $join = $stmt->join !== null && $stmt->join !== [] ? ' ' . JoinKeyword::build($stmt->join) : '';
        return $from . $join;
    }

    /**
     * Preserve the original WHERE expression and parsed ordering and limit clauses.
     */
    public function suffix(DeleteStatement $stmt, string $originalSql): string
    {
        $where = (new DmlWhereClauseExtractor())->extract($originalSql);
        $whereClause = $where !== null && $where !== '' ? ' WHERE ' . $where : '';
        $orders = [];
        foreach ($stmt->order ?? [] as $order) {
            $orders[] = OrderKeyword::build($order);
        }
        $orderClause = $orders === [] ? '' : ' ORDER BY ' . implode(', ', $orders);
        $limitClause = $stmt->limit !== null ? ' LIMIT ' . Limit::build($stmt->limit) : '';
        return ' ' . $whereClause . $orderClause . $limitClause;
    }

    /**
     * @param array<int, string> $columns
     */
    public function selectList(string $alias, array $columns): string
    {
        if ($columns === []) {
            return "`$alias`.*";
        }
        $parts = [];
        foreach ($columns as $column) {
            $parts[] = "`$alias`.`$column` AS `$column`";
        }
        return implode(', ', $parts);
    }

    /**
     * @param array{table: string, alias: string, aliases: array<string, string>} $target
     * @return array<string, array{alias: string}>
     */
    public function resolvedTables(DeleteStatement $stmt, array $target): array
    {
        if ($target['aliases'] === []) {
            return [$target['table'] => ['alias' => $target['alias']]];
        }
        $tables = [];
        foreach ($target['aliases'] as $alias) {
            $name = (new TargetProjection())->resolveAliasToTable($alias, $stmt);
            if ($name !== null) {
                $tables[$name] = ['alias' => $alias];
            }
        }
        return $tables;
    }
}
