<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use RuntimeException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Dialect\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\Parse\DmlWhereClauseExtractor;
use ZtdQuery\Platform\MySql\Parse\MySqlParser;
use ZtdQuery\Platform\MySql\Rewrite\MySqlComponentSql;
use ZtdQuery\Platform\MySql\Rewrite\MySqlCteShadowComposer;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Shadow\Mutation\MultiTableMutationRow;
use ZtdQuery\Shadow\Mutation\MultiTableMutationTarget;

/**
 * Transforms DELETE statements into SELECT projections with CTE shadowing.
 *
 * @phpstan-import-type ShadowTables from SqlTransformer
 */
final class DeleteTransformer implements SqlTransformer
{
    private MySqlParser $parser;
    private SelectTransformer $selectTransformer;
    private MySqlCteShadowComposer $cteComposer;

    /**
     * Binds the instance to what it will work from.
     *
     * @param MySqlParser $parser
     * @param SelectTransformer $selectTransformer
     */
    public function __construct(
        MySqlParser $parser,
        SelectTransformer $selectTransformer,
        private readonly MySqlComponentSql $components = new MySqlComponentSql(),
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->cteComposer = new MySqlCteShadowComposer();
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedSqlException
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
        if ($targetTable !== null && isset($tables[$targetTable]['columns'])) {
            $columnNames = $tables[$targetTable]['columns'];
        }

        $projection = $this->buildProjection($statement, $sql, $columnNames);
        $targetTableNames = array_keys($projection['tables']);
        if (isset($targetTableNames[1])) {
            $targets = $this->targetsFromContexts($projection['tables'], $tables);
            $projection = $this->buildProjection($statement, $sql, $columnNames, $targets);
        }

        return $this->selectTransformer->transform(
            $this->cteComposer->carryPrefix($sql, $projection['sql']),
            $tables,
        );
    }

    /**
     * Build a result-select SQL and resolve the target table(s).
     *
     * @param DeleteStatement $stmt
     * @param string $originalSql
     * @param array<int, string> $columns
     * @param list<MultiTableMutationTarget> $targets
     * @return array{sql: string, table: string, tables: array<string, array{alias: string}>}
     *
     * @throws RuntimeException
     */
    public function buildProjection(DeleteStatement $stmt, string $originalSql, array $columns, array $targets = []): array
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
                    throw new RuntimeException('Delete target table could not be resolved.');
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
            throw new RuntimeException('ZTD Write Protection: PARTITION clause in DELETE is not supported (cannot simulate safely).');
        }

        $fromClause = '';
        if ($stmt->from !== null && $stmt->from !== []) {
            $fromParts = [];
            foreach ($stmt->from as $expr) {
                $fromParts[] = $this->components->expression($expr, $originalSql);
            }
            $fromClause = ' FROM ' . implode(', ', $fromParts);
        }

        $joinClause = '';
        if ($stmt->join !== null && $stmt->join !== []) {
            $joinClause = ' ' . $this->components->joins($stmt->join, $originalSql);
        }

        $usingClause = '';
        if ($stmt->using !== null && $stmt->using !== []) {
            $usingParts = [];
            foreach ($stmt->using as $expr) {
                $usingParts[] = $this->components->expression($expr, $originalSql);
            }
            $fromClause = ' FROM ' . implode(', ', $usingParts);
        }

        $whereClause = '';
        $whereExpression = (new DmlWhereClauseExtractor())->extract($originalSql);
        if ($whereExpression !== null && $whereExpression !== '') {
            $whereClause = ' WHERE ' . $whereExpression;
        }

        $orderClause = '';
        if ($stmt->order !== null && $stmt->order !== []) {
            $orderParts = [];
            foreach ($stmt->order as $order) {
                $orderParts[] = $this->components->order($order, $originalSql);
            }
            $orderClause = ' ORDER BY ' . implode(', ', $orderParts);
        }

        $limitClause = '';
        if ($stmt->limit !== null) {
            $limitClause = ' LIMIT ' . $this->components->limit($stmt->limit, $originalSql);
        }

        $targetTableAlias = $targetTableAlias ?? $targetTableName;
        if ($targetTableAlias === null || $targetTableAlias === '') {
            throw new RuntimeException('Delete target table could not be resolved.');
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
            throw new RuntimeException('Delete target table could not be resolved.');
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

        if ($targets !== []) {
            $selectList = $this->multiTableSelectList($resolvedTables, $targets);
            $sql = "SELECT $selectList$fromClause$joinClause$usingClause $whereClause$orderClause$limitClause";
        }

        return ['sql' => $sql, 'table' => $targetTableName, 'tables' => $resolvedTables];
    }

    /**
     * @param array<string, array{alias: string}> $resolvedTables
     * @param ShadowTables $contexts Table name => what the shadow holds for it
     *
     * @return list<MultiTableMutationTarget> One target per table the statement deletes from that the shadow knows
     */
    public function targetsFromContexts(array $resolvedTables, array $contexts): array
    {
        $targets = [];
        foreach ($resolvedTables as $tableName => $tableInfo) {
            $context = $contexts[$tableName] ?? null;
            if (!isset($context['columns'])) {
                continue;
            }
            $targets[] = new MultiTableMutationTarget(
                $tableName,
                $context['columns'],
                $context['primaryKeys'] ?? [],
            );
        }

        return $targets;
    }

    /**
     * Writes the select list that carries each deleted row's key back.
     *
     * A statement deleting from several tables answers one result set, so
     * each table's key columns are carried under names of their own that no
     * table would use.
     *
     * @param array<string, array{alias: string}> $resolvedTables Table name => the name the statement gave it
     * @param list<MultiTableMutationTarget> $targets The tables being deleted from
     *
     * @return string The select list
     */
    public function multiTableSelectList(array $resolvedTables, array $targets): string
    {
        $codec = new MultiTableMutationRow();
        $quoter = new MySqlIdentifierQuoter();
        $parts = [];
        foreach ($targets as $targetIndex => $target) {
            $tableInfo = $resolvedTables[$target->tableName()] ?? null;
            if ($tableInfo === null) {
                continue;
            }
            foreach ($target->matchColumns() as $columnIndex => $column) {
                $alias = $quoter->quote($tableInfo['alias']);
                $quotedColumn = $quoter->quote($column);
                $metadata = $quoter->quote($codec->valueColumn($targetIndex, $columnIndex));
                $parts[] = "$alias.$quotedColumn AS $metadata";
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Answers which table a name in the statement stands for.
     *
     * A name the statement never gave to anything is taken to be a table's
     * own name, because that is what MySQL takes it to be.
     *
     * @param string $alias Name written in the statement
     * @param DeleteStatement $stmt The statement, as the parser reads it
     *
     * @return string|null The table it stands for, or null where the statement names it as nothing
     */
    public function resolveAliasToTable(string $alias, DeleteStatement $stmt): ?string
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
     * Answers the table an expression names.
     *
     * The parser fills in the table separately once it has read a qualified
     * name, and leaves the whole expression where it has not, so both have to
     * be read -- the more specific first.
     *
     * @param Expression $expr Expression to read
     *
     * @return string|null The table, or null where the expression names none
     */
    public static function exprTable(Expression $expr): ?string
    {
        return (($expr->table ?? '') !== '') ? $expr->table : $expr->expr;
    }

    /**
     * Answers the name an expression is known by in the statement.
     *
     * A table the statement gave no name to is known by its own.
     *
     * @param Expression $expr Expression to read
     *
     * @return string|null The name, or null where the expression names nothing
     */
    public static function exprAlias(Expression $expr): ?string
    {
        return (($expr->alias ?? '') !== '') ? $expr->alias : self::exprTable($expr);
    }
}
