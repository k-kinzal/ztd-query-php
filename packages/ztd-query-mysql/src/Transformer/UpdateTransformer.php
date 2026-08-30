<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use RuntimeException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Dialect\MySqlComponentSql;
use ZtdQuery\Platform\MySql\Parse\DmlWhereClauseExtractor;
use ZtdQuery\Platform\MySql\Parse\MySqlParser;
use ZtdQuery\Platform\MySql\Parse\UpdateAssignmentExtractor;
use ZtdQuery\Platform\MySql\Parse\UpdateSourceExtractor;
use ZtdQuery\Platform\MySql\Rewrite\MySqlCteShadowComposer;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Shadow\Mutation\Row\MultiTableMutationTarget;

/**
 * Transforms UPDATE statements into SELECT projections with CTE shadowing.
 *
 * @phpstan-import-type ShadowTables from SqlTransformer
 */
final class UpdateTransformer implements SqlTransformer
{
    private MySqlParser $parser;
    private SelectTransformer $selectTransformer;
    private MySqlCteShadowComposer $cteComposer;
    private MySqlUpdateClauses $clauses;

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
        ?MySqlUpdateClauses $clauses = null,
        private readonly MySqlUpdateSelectList $selectList = new MySqlUpdateSelectList(),
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->cteComposer = new MySqlCteShadowComposer();
        $this->clauses = $clauses ?? new MySqlUpdateClauses($this->components);
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedSqlException
     */
    public function transform(string $sql, array $tables): string
    {
        $statement = $this->requireUpdate($sql);
        $targetTable = $this->requireTargetTable($statement, $sql);
        $columns = $tables[$targetTable]['columns'] ?? [];
        $primaryKeys = $tables[$targetTable]['primaryKeys'] ?? [];
        $assignmentValues = (new UpdateAssignmentExtractor())->values($sql);
        $whereExpression = (new DmlWhereClauseExtractor())->extract($sql);
        $sourceExpression = (new UpdateSourceExtractor())->extract($sql);

        $projection = $this->buildProjection(
            $statement,
            $columns,
            $primaryKeys,
            [],
            $assignmentValues,
            $whereExpression,
            $sourceExpression,
        );
        if (count($projection['tables']) > 1) {
            $projection = $this->buildProjection(
                $statement,
                $columns,
                $primaryKeys,
                $this->targetsFromContexts($projection['tables'], $tables),
                $assignmentValues,
                $whereExpression,
                $sourceExpression,
            );
        }

        return $this->selectTransformer->transform(
            $this->cteComposer->carryPrefix($sql, $projection['sql']),
            $tables,
        );
    }

    /**
     * Reads a statement as the UPDATE it has to be for ZTD to simulate it.
     *
     * The shadow holds a table's rows and not the partitions they are kept
     * in, so a statement naming one would change rows in all of them.
     *
     * @param string $sql The statement, as written
     *
     * @return UpdateStatement The statement, as the parser reads it
     *
     * @throws UnsupportedSqlException When it is not an UPDATE, or names a partition
     */
    public function requireUpdate(string $sql): UpdateStatement
    {
        $statement = $this->parser->parse($sql)[0] ?? null;
        if (!$statement instanceof UpdateStatement) {
            throw new UnsupportedSqlException($sql, 'Expected UPDATE statement');
        }
        if (preg_match('/\bPARTITION\s*\(([^)]+)\)/i', $sql) === 1) {
            throw new UnsupportedSqlException($sql, 'PARTITION clause not supported');
        }

        return $statement;
    }

    /**
     * Answers the table an UPDATE writes to first, insisting it names one.
     *
     * @param UpdateStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return string The table
     *
     * @throws UnsupportedSqlException When the statement names no table to write to
     */
    public function requireTargetTable(UpdateStatement $statement, string $sql): string
    {
        $target = $statement->tables[0] ?? null;
        if ($target === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve UPDATE target');
        }
        $table = self::exprTable($target);
        if ($table === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        return $table;
    }

    /**
     * Build a result-select SQL from an UPDATE statement.
     *
     * @param UpdateStatement $stmt
     * @param array<int, string> $columns
     * @param array<int, string> $primaryKeys
     * @param list<MultiTableMutationTarget> $targets
     * @param list<string> $assignmentValues
     * @return array{sql: string, table: string, tables: array<string, array{alias: string}>}
     *
     * @throws RuntimeException
     */
    public function buildProjection(
        UpdateStatement $stmt,
        array $columns,
        array $primaryKeys = [],
        array $targets = [],
        array $assignmentValues = [],
        ?string $whereExpression = null,
        ?string $sourceExpression = null,
    ): array {
        $targetTableExpr = $stmt->tables[0] ?? null;
        if ($targetTableExpr === null) {
            throw new RuntimeException('Update statement has no tables?');
        }
        $targetTableName = $targetTableExpr->table;
        if ($targetTableName === null || $targetTableName === '') {
            throw new RuntimeException('Update statement target table name is empty.');
        }

        $alias = $targetTableExpr->alias ?? '';
        $qualifier = $alias !== '' ? $alias : $targetTableName;
        $allTargetTables = $this->writtenTables($stmt);
        $selectCols = $targets === []
            ? $this->selectList->selectColumns($stmt, $columns, $primaryKeys, $assignmentValues, $qualifier)
            : $this->selectList->multiTableSelectColumns($stmt, $allTargetTables, $targets, $assignmentValues);
        $selectList = $selectCols === [] ? '*' : implode(', ', $selectCols);

        $additionalTables = $this->buildAdditionalTables($stmt);
        $joinClause = $this->buildJoinClause($stmt);
        $whereClause = $this->clauses->whereClause($stmt, $whereExpression);
        $orderByClause = $this->clauses->orderClause($stmt);
        $limitClause = $this->clauses->limitClause($stmt);
        $aliasClause = $alias !== '' ? ' AS ' . $alias : '';
        $sourceClause = $sourceExpression ?? "`{$targetTableName}`{$aliasClause}{$additionalTables}{$joinClause}";
        $read = "{$sourceClause}{$whereClause}{$orderByClause}{$limitClause}";
        $reachesOneTableOnly = $additionalTables === '' && $joinClause === '';
        $sql = $reachesOneTableOnly && ($orderByClause !== '' || $limitClause !== '')
            ? "SELECT {$selectList} FROM (SELECT * FROM {$read}) AS `{$qualifier}`"
            : "SELECT {$selectList} FROM {$read}";

        return ['sql' => $sql, 'table' => $targetTableName, 'tables' => $allTargetTables];
    }

    /**
     * Answers every table the statement writes to, by its own name.
     *
     * @param UpdateStatement $stmt The statement, as the parser reads it
     *
     * @return array<string, array{alias: string}> Table name => the name the statement gave it
     */
    public function writtenTables(UpdateStatement $stmt): array
    {
        $tables = [];
        foreach ($stmt->tables ?? [] as $tableExpr) {
            $tableName = self::exprTable($tableExpr) ?? '';
            if ($tableName !== '') {
                $tables[$tableName] = ['alias' => $tableExpr->alias ?? $tableName];
            }
        }

        return $tables;
    }

    /**
     * @param array<string, array{alias: string}> $targetTables
     * @param ShadowTables $contexts Table name => what the shadow holds for it
     *
     * @return list<MultiTableMutationTarget> One target per table the statement writes to that the shadow knows
     */
    public function targetsFromContexts(array $targetTables, array $contexts): array
    {
        $targets = [];
        foreach ($targetTables as $tableName => $tableInfo) {
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
     * Writes the tables a multi-table UPDATE names after the first.
     *
     * @param UpdateStatement $stmt The statement, as the parser reads it
     *
     * @return string The rest of the FROM clause, opening with a comma, or nothing where the statement names one table
     */
    public function buildAdditionalTables(UpdateStatement $stmt): string
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

    /**
     * Writes the joins an UPDATE was written with.
     *
     * The parser reads a join's kind as written, and a bare JOIN as nothing
     * at all, so what it read is completed rather than taken as it stands.
     *
     * @param UpdateStatement $stmt The statement, as the parser reads it
     *
     * @return string The joins, as written, or nothing where the statement joins nothing
     */
    public function buildJoinClause(UpdateStatement $stmt): string
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
                    $onParts[] = $condition->expr !== '' ? $condition->expr : $this->components->condition([$condition], $stmt->build());
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
        return (($expr->table ?? '') !== '') ? $expr->table : ($expr->expr ?? null);
    }
}
