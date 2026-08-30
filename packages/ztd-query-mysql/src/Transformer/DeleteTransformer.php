<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use RuntimeException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Dialect\MySqlComponentSql;
use ZtdQuery\Platform\MySql\Dialect\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\Parse\MySqlParser;
use ZtdQuery\Platform\MySql\Rewrite\MySqlCteShadowComposer;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Shadow\Mutation\Row\MultiTableMutationRow;
use ZtdQuery\Shadow\Mutation\Row\MultiTableMutationTarget;

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

    private MySqlDeleteClauses $clauses;

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
        private readonly MySqlDeleteTargets $targets = new MySqlDeleteTargets(),
        ?MySqlDeleteClauses $clauses = null,
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->cteComposer = new MySqlCteShadowComposer();
        $this->clauses = $clauses ?? new MySqlDeleteClauses($this->components);
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
        $this->refusePartitionClause($originalSql);
        $target = $this->targets->of($stmt);
        $clauses = $this->clauses->of($stmt, $originalSql);
        $resolvedTables = $this->resolvedTables($stmt, $target);
        $selectList = $targets === []
            ? $this->singleTableSelectList($target['alias'], $columns)
            : $this->multiTableSelectList($resolvedTables, $targets);

        return [
            'sql' => "SELECT {$selectList}{$clauses}",
            'table' => $target['name'],
            'tables' => $resolvedTables,
        ];
    }

    /**
     * Refuses a DELETE naming the partitions it removes rows from.
     *
     * The shadow holds a table's rows and not the partitions they are kept
     * in, so a statement asking for one partition would remove rows from all
     * of them.
     *
     * @param string $sql The statement, as written
     *
     * @throws RuntimeException When the statement names a partition
     */
    public function refusePartitionClause(string $sql): void
    {
        if (preg_match('/\bPARTITION\s*\(([^)]+)\)/i', $sql) === 1) {
            throw new RuntimeException(
                'ZTD Write Protection: PARTITION clause in DELETE is not supported (cannot simulate safely).',
            );
        }
    }

    /**
     * Answers each table the statement deletes from, by its own name.
     *
     * @param DeleteStatement $stmt The statement, as the parser reads it
     * @param array{name: string, alias: string} $target The table it deletes from, where it names only one
     *
     * @return array<string, array{alias: string}> Table name => the name the statement gave it
     */
    public function resolvedTables(DeleteStatement $stmt, array $target): array
    {
        $named = $this->targets->namedAliases($stmt);
        if ($named === []) {
            return [$target['name'] => ['alias' => $target['alias']]];
        }

        $resolved = [];
        foreach ($named as $alias) {
            $name = $this->resolveAliasToTable($alias, $stmt);
            if ($name !== null) {
                $resolved[$name] = ['alias' => $alias];
            }
        }

        return $resolved;
    }

    /**
     * Writes the select list that carries one table's deleted rows back.
     *
     * @param string $alias The name the statement gave the table
     * @param array<int, string> $columns Columns to carry back, or none to carry them all
     *
     * @return string The select list
     */
    public function singleTableSelectList(string $alias, array $columns): string
    {
        if ($columns === []) {
            return "`{$alias}`.*";
        }

        $parts = [];
        foreach ($columns as $column) {
            $parts[] = "`{$alias}`.`{$column}` AS `{$column}`";
        }

        return implode(', ', $parts);
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
