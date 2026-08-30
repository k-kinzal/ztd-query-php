<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use RuntimeException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Dialect\MySqlComponentSql;
use ZtdQuery\Platform\MySql\Dialect\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\Parse\DmlWhereClauseExtractor;
use ZtdQuery\Platform\MySql\Parse\MySqlParser;
use ZtdQuery\Platform\MySql\Parse\UpdateAssignmentExtractor;
use ZtdQuery\Platform\MySql\Parse\UpdateSourceExtractor;
use ZtdQuery\Platform\MySql\Rewrite\MySqlCteShadowComposer;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\Mutation\Row\MultiTableMutationRow;
use ZtdQuery\Shadow\Mutation\Row\MultiTableMutationTarget;
use ZtdQuery\Sql\SqlTokenStream;

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
        $targetTableNames = array_keys($projection['tables']);
        if (isset($targetTableNames[1])) {
            $targets = $this->targetsFromContexts($projection['tables'], $tables);
            $projection = $this->buildProjection(
                $statement,
                $columns,
                $primaryKeys,
                $targets,
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
        if ($stmt->tables === null || $stmt->tables === []) {
            throw new RuntimeException('Update statement has no tables?');
        }
        $targetTableExpr = $stmt->tables[0];
        $targetTableName = $targetTableExpr->table;
        if ($targetTableName === null || $targetTableName === '') {
            throw new RuntimeException('Update statement target table name is empty.');
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
            foreach ($stmt->set as $index => $setOp) {
                $colName = $setOp->column;
                $colName = trim($colName, '`"\'');
                if (str_contains($colName, '.')) {
                    $parts = explode('.', $colName);
                    $colName = trim(end($parts), '`"\'');
                }

                $selectCols[] = ($assignmentValues[$index] ?? $setOp->value) . ' AS `' . $colName . '`';
                $coveredCols[$colName] = true;
            }
        }

        foreach ($columns as $col) {
            if (!isset($coveredCols[$col])) {
                $selectCols[] = "`$qualifier`.`$col`";
            }
        }

        $identity = new MutationRowIdentity();
        foreach ($primaryKeys as $primaryKey) {
            $selectCols[] = "`$qualifier`.`$primaryKey` AS `" . $identity->column($primaryKey) . '`';
        }

        if ($targets !== []) {
            $selectCols = $this->multiTableSelectColumns($stmt, $allTargetTables, $targets, $assignmentValues);
        }

        if ($selectCols === []) {
            $selectCols[] = '*';
        }
        $selectList = implode(', ', $selectCols);

        $aliasClause = '';
        if (($targetTableExpr->alias ?? '') !== '') {
            $aliasClause = ' AS ' . $targetTableExpr->alias;
        }

        $additionalTables = $this->buildAdditionalTables($stmt);

        $joinClause = $this->buildJoinClause($stmt);

        $whereClause = '';
        if ($whereExpression === null) {
            $whereExpression = $this->components->condition($stmt->where ?? [], $stmt->build());
        }
        if ($whereExpression !== '') {
            $whereClause = ' WHERE ' . $whereExpression;
        }

        $orderByClause = '';
        if ($stmt->order !== null && $stmt->order !== []) {
            $orderParts = [];
            foreach ($stmt->order as $orderExpr) {
                $orderParts[] = $this->components->order($orderExpr, $stmt->build());
            }
            $orderByClause = ' ORDER BY ' . implode(', ', $orderParts);
        }

        $limitClause = '';
        if ($stmt->limit !== null) {
            $limitClause = ' LIMIT ' . $this->components->limit($stmt->limit, $stmt->build());
        }

        $sourceClause = $sourceExpression ?? "`$targetTableName`$aliasClause$additionalTables$joinClause";
        if ($additionalTables === '' && $joinClause === '' && ($orderByClause !== '' || $limitClause !== '')) {
            $selectedRows = "SELECT * FROM $sourceClause$whereClause$orderByClause$limitClause";
            $sql = "SELECT $selectList FROM ($selectedRows) AS `$qualifier`";
        } else {
            $sql = "SELECT $selectList FROM $sourceClause$whereClause$orderByClause$limitClause";
        }

        return ['sql' => $sql, 'table' => $targetTableName, 'tables' => $allTargetTables];
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
     * Writes the select list that carries every changed row back, and the key it had.
     *
     * A column the statement does not assign carries what it already held, so
     * the row read back is the whole row as it would become. Its key is
     * carried separately, because assigning to a key column changes it, and
     * the row still has to be found by the key it had.
     *
     * @param UpdateStatement $stmt The statement, as the parser reads it
     * @param array<string, array{alias: string}> $targetTables Table name => the name the statement gave it
     * @param list<MultiTableMutationTarget> $targets The tables being written to
     * @param list<string> $assignmentValues What each assignment assigns, in the order written
     *
     * @return list<string> The select list, one entry per column carried back
     */
    public function multiTableSelectColumns(
        UpdateStatement $stmt,
        array $targetTables,
        array $targets,
        array $assignmentValues,
    ): array {
        $assignments = $this->assignmentsByTable($stmt, $targetTables, $assignmentValues);
        $codec = new MultiTableMutationRow();
        $quoter = new MySqlIdentifierQuoter();
        $selectColumns = [];
        foreach ($targets as $targetIndex => $target) {
            $tableInfo = $targetTables[$target->tableName()] ?? null;
            if ($tableInfo === null) {
                continue;
            }
            $alias = $quoter->quote($tableInfo['alias']);
            foreach ($target->columns() as $columnIndex => $column) {
                $value = $assignments[$target->tableName()][$column] ?? $alias . '.' . $quoter->quote($column);
                $metadata = $quoter->quote($codec->valueColumn($targetIndex, $columnIndex));
                $selectColumns[] = "$value AS $metadata";
            }
            foreach ($target->primaryKeys() as $primaryKeyIndex => $primaryKey) {
                $metadata = $quoter->quote($codec->identityColumn($targetIndex, $primaryKeyIndex));
                $selectColumns[] = $alias . '.' . $quoter->quote($primaryKey) . " AS $metadata";
            }
        }

        return $selectColumns;
    }

    /**
     * Answers what each assignment assigns, under the table it writes to.
     *
     * An assignment to a bare column name writes to the first table the
     * statement names, which is what MySQL does with one.
     *
     * @param UpdateStatement $stmt The statement, as the parser reads it
     * @param array<string, array{alias: string}> $targetTables Table name => the name the statement gave it
     * @param list<string> $assignmentValues What each assignment assigns, in the order written
     *
     * @return array<string, array<string, string>> Table => column => what is assigned to it
     */
    public function assignmentsByTable(UpdateStatement $stmt, array $targetTables, array $assignmentValues): array
    {
        $assignments = [];
        $primaryTable = array_key_first($targetTables);
        $qualifiedTables = [];
        foreach ($targetTables as $tableName => $tableInfo) {
            $qualifiedTables[$tableName] = $tableName;
            $qualifiedTables[$tableInfo['alias']] = $tableName;
        }
        foreach ($stmt->set ?? [] as $index => $setOperation) {
            $parts = array_map(self::unquoteIdentifier(...), explode('.', $setOperation->column));
            $column = array_pop($parts);
            if ($column === '') {
                continue;
            }
            $tableName = $primaryTable;
            $qualifier = array_pop($parts);
            if ($qualifier !== null) {
                $tableName = $qualifiedTables[$qualifier] ?? $primaryTable;
            }
            if ($tableName !== null) {
                $assignments[$tableName][$column] = $assignmentValues[$index] ?? $setOperation->value;
            }
        }

        return $assignments;
    }

    /**
     * Answers the name a written identifier stands for.
     *
     * @param string $identifier The name, as it was written
     *
     * @return string The name, with the quoting taken off
     */
    public static function unquoteIdentifier(string $identifier): string
    {
        return SqlTokenStream::tokenize($identifier, MySqlLexerProfile::create())->identifierAt()['name'] ?? $identifier;
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
