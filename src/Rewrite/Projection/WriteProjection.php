<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite\Projection;

use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\Shadowing\CteShadowing;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\MultiDeleteMutation;
use ZtdQuery\Shadow\Mutation\MultiUpdateMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Mutation\UpsertMutation;
use ZtdQuery\Shadow\ShadowStore;
use PhpMyAdmin\SqlParser\Components\ArrayObj;
use PhpMyAdmin\SqlParser\Components\CaseExpression;
use PhpMyAdmin\SqlParser\Components\Condition;
use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Components\GroupKeyword;
use PhpMyAdmin\SqlParser\Components\Limit;
use PhpMyAdmin\SqlParser\Components\OrderKeyword;
use PhpMyAdmin\SqlParser\Components\SetOperation;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use RuntimeException;

/**
 * Projects write statements into result-select queries.
 */
final class WriteProjection
{
    /**
     * Shadow store holding simulated rows.
     *
     * @var ShadowStore
     */
    private ShadowStore $shadowStore;

    /**
     * Schema registry for column and key lookup.
     *
     * @var SchemaRegistry
     */
    private SchemaRegistry $schemaRegistry;

    /**
     * CTE shadowing helper for result-select queries.
     *
     * @var CteShadowing
     */
    private CteShadowing $shadowing;

    /**
     * UPDATE transformer used for projections.
     *
     * @var UpdateTransformer
     */
    private UpdateTransformer $updateTransformer;

    /**
     * DELETE transformer used for projections.
     *
     * @var DeleteTransformer
     */
    private DeleteTransformer $deleteTransformer;

    /**
     * @param ShadowStore $shadowStore Shadow state for INSERT/UPDATE/DELETE.
     * @param SchemaRegistry $schemaRegistry Schema lookups for columns/PKs.
     * @param CteShadowing $shadowing CTE envelope builder.
     * @param UpdateTransformer $updateTransformer UPDATE projector.
     * @param DeleteTransformer $deleteTransformer DELETE projector.
     */
    public function __construct(
        ShadowStore $shadowStore,
        SchemaRegistry $schemaRegistry,
        CteShadowing $shadowing,
        UpdateTransformer $updateTransformer,
        DeleteTransformer $deleteTransformer
    ) {
        $this->shadowStore = $shadowStore;
        $this->schemaRegistry = $schemaRegistry;
        $this->shadowing = $shadowing;
        $this->updateTransformer = $updateTransformer;
        $this->deleteTransformer = $deleteTransformer;
    }

    /**
     * Project a write statement into a rewrite plan.
     */
    public function project(string $sql, UpdateStatement|DeleteStatement|InsertStatement $statement): RewritePlan
    {
        if ($statement instanceof UpdateStatement) {
            return $this->projectUpdate($statement, $sql);
        }

        if ($statement instanceof DeleteStatement) {
            return $this->projectDelete($statement, $sql);
        }

        return $this->projectInsert($statement, $sql);
    }

    private function projectUpdate(UpdateStatement $statement, string $originalSql): RewritePlan
    {
        // Check for PARTITION clause which is not supported
        if (preg_match('/\bPARTITION\s*\(([^)]+)\)/i', $originalSql)) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        if ($statement->tables === [] || !isset($statement->tables[0])) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        $targetExpr = $statement->tables[0];
        $targetTable = $targetExpr->table ?? $targetExpr->expr ?? null;
        if ($targetTable === null) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }
        $this->shadowStore->ensure($targetTable);

        $columns = $this->shadowStore->get($targetTable);
        $columnNames = $columns !== [] ? array_keys($columns[0]) : null;
        if ($columnNames === null) {
            $columnNames = $this->schemaRegistry->getColumns($targetTable);
        }
        if ($columnNames === null) {
            return new RewritePlan($originalSql, QueryKind::UNKNOWN_SCHEMA, null, $targetTable);
        }

        $projection = $this->updateTransformer->build($statement, $columnNames);
        $shadowedSql = $this->shadowing->apply($projection['sql'], $this->shadowStore->getAll());

        // Check if this is a multi-table UPDATE
        $tables = $projection['tables'];
        if (count($tables) > 1) {
            // Multi-table UPDATE - ensure all tables exist and get their primary keys
            /** @var array<string, array<int, string>> $tablesPrimaryKeys */
            $tablesPrimaryKeys = [];
            foreach ($tables as $tableName => $tableInfo) {
                // Check if table exists in schema or shadow store before getting primary keys
                $existingColumns = $this->schemaRegistry->getColumns($tableName);
                $existingRows = $this->shadowStore->get($tableName);
                if ($existingColumns === null && $existingRows === []) {
                    // Table doesn't exist - return UNKNOWN_SCHEMA
                    return new RewritePlan($originalSql, QueryKind::UNKNOWN_SCHEMA, null, $tableName);
                }
                $this->shadowStore->ensure($tableName);
                $tablesPrimaryKeys[$tableName] = $this->schemaRegistry->getPrimaryKeys($tableName);
            }
            return new RewritePlan($shadowedSql, QueryKind::WRITE_SIMULATED, new MultiUpdateMutation($tablesPrimaryKeys));
        }

        $primaryKeys = $this->schemaRegistry->getPrimaryKeys($targetTable);
        return new RewritePlan($shadowedSql, QueryKind::WRITE_SIMULATED, new UpdateMutation($targetTable, $primaryKeys));
    }

    private function projectDelete(DeleteStatement $statement, string $originalSql): RewritePlan
    {
        $targetTable = null;
        if ($statement->from !== null && $statement->from !== []) {
            $targetExpr = $statement->from[0];
            $targetTable = $targetExpr->table ?? $targetExpr->expr ?? null;
        }

        $columnNames = [];
        if ($targetTable !== null) {
            $rows = $this->shadowStore->get($targetTable);
            $columnNames = $rows !== [] ? array_keys($rows[0]) : ($this->schemaRegistry->getColumns($targetTable) ?? []);
        }

        $projection = $this->deleteTransformer->build($statement, $originalSql, $columnNames);
        $targetTable = $projection['table'];

        // Check if target table name is valid (not 'unknown' which indicates a parse failure)
        if ($targetTable === 'unknown') {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        $this->shadowStore->ensure($targetTable);

        $shadowedSql = $this->shadowing->apply($projection['sql'], $this->shadowStore->getAll());

        // Check if this is a multi-table DELETE
        $tables = $projection['tables'];
        if (count($tables) > 1) {
            // Multi-table DELETE - ensure all tables exist and get their primary keys
            /** @var array<string, array<int, string>> $tablesPrimaryKeys */
            $tablesPrimaryKeys = [];
            foreach ($tables as $tableName => $tableInfo) {
                // Check if table exists in schema or shadow store before getting primary keys
                $existingColumns = $this->schemaRegistry->getColumns($tableName);
                $existingRows = $this->shadowStore->get($tableName);
                if ($existingColumns === null && $existingRows === []) {
                    // Table doesn't exist - return UNKNOWN_SCHEMA
                    return new RewritePlan($originalSql, QueryKind::UNKNOWN_SCHEMA, null, $tableName);
                }
                $this->shadowStore->ensure($tableName);
                $tablesPrimaryKeys[$tableName] = $this->schemaRegistry->getPrimaryKeys($tableName);
            }
            return new RewritePlan($shadowedSql, QueryKind::WRITE_SIMULATED, new MultiDeleteMutation($tablesPrimaryKeys));
        }

        // Check if table exists before getting primary keys for single-table DELETE
        $existingColumns = $this->schemaRegistry->getColumns($targetTable);
        $existingRows = $this->shadowStore->get($targetTable);
        if ($existingColumns === null && $existingRows === []) {
            return new RewritePlan($originalSql, QueryKind::UNKNOWN_SCHEMA, null, $targetTable);
        }

        $primaryKeys = $this->schemaRegistry->getPrimaryKeys($targetTable);
        return new RewritePlan($shadowedSql, QueryKind::WRITE_SIMULATED, new DeleteMutation($targetTable, $primaryKeys));
    }

    private function projectInsert(InsertStatement $statement, string $originalSql): RewritePlan
    {
        if ($statement->into === null || $statement->into->dest === null) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        $dest = $statement->into->dest;
        $tableName = is_string($dest) ? $dest : ($dest->table ?? null);
        if ($tableName === null) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        // Handle INSERT ... ON DUPLICATE KEY UPDATE
        $updateColumns = [];
        /** @var array<string, string> $updateValues */
        $updateValues = [];
        if ($statement->onDuplicateSet !== null && $statement->onDuplicateSet !== []) {
            foreach ($statement->onDuplicateSet as $set) {
                $colName = trim($set->column, '`"\'');
                if (str_contains($colName, '.')) {
                    $parts = explode('.', $colName);
                    $colName = trim(end($parts), '`"\'');
                }
                $updateColumns[] = $colName;
                $updateValues[$colName] = $set->value;
            }
        }
        $isOnDuplicateKeyUpdate = $updateColumns !== [];

        $columns = $statement->into->columns ?? [];
        $columns = array_values(array_filter($columns, 'is_string'));
        if ($columns === []) {
            $columns = $this->schemaRegistry->getColumns($tableName) ?? [];
        }
        if ($columns === []) {
            return new RewritePlan($originalSql, QueryKind::UNKNOWN_SCHEMA, null, $tableName);
        }

        $selectSql = $this->buildInsertSelect($statement, $columns);
        $shadowedSql = $this->shadowing->apply($selectSql, $this->shadowStore->getAll());

        // Check for INSERT IGNORE
        $isIgnore = $statement->options !== null && $statement->options->has('IGNORE');

        // Determine the mutation type
        if ($isOnDuplicateKeyUpdate) {
            $primaryKeys = $this->schemaRegistry->getPrimaryKeys($tableName);
            return new RewritePlan($shadowedSql, QueryKind::WRITE_SIMULATED, new UpsertMutation($tableName, $primaryKeys, $updateColumns, $updateValues));
        }

        $primaryKeys = $isIgnore ? $this->schemaRegistry->getPrimaryKeys($tableName) : [];
        return new RewritePlan($shadowedSql, QueryKind::WRITE_SIMULATED, new InsertMutation($tableName, $primaryKeys, $isIgnore));
    }

    /**
     * @param array<int, string> $columns
     */
    private function buildInsertSelect(InsertStatement $statement, array $columns): string
    {
        if ($statement->values !== null && $statement->values !== []) {
            $rows = [];
            foreach ($statement->values as $valueSet) {
                $rows[] = $this->buildInsertRowSelect($valueSet, $columns);
            }

            return implode(' UNION ALL ', $rows);
        }

        if ($statement->set !== null && $statement->set !== []) {
            return $this->buildInsertSetSelect(array_values($statement->set));
        }

        if ($statement->select !== null) {
            return $this->buildInsertFromSelect($statement, $columns);
        }

        throw new RuntimeException('Insert statement has no values to project.');
    }

    /**
     * @param array<int, string> $columns
     */
    private function buildInsertRowSelect(ArrayObj $valueSet, array $columns): string
    {
        $values = $valueSet->raw !== [] ? $valueSet->raw : $valueSet->values;
        if (count($values) !== count($columns)) {
            throw new RuntimeException('Insert values count does not match column count.');
        }

        $selects = [];
        foreach ($columns as $index => $column) {
            $expr = trim($values[$index]);
            $selects[] = $expr . ' AS `' . $column . '`';
        }

        return 'SELECT ' . implode(', ', $selects);
    }

    /**
     * @param array<int, SetOperation> $setOperations
     */
    private function buildInsertSetSelect(array $setOperations): string
    {
        $selects = [];
        foreach ($setOperations as $set) {
            $selects[] = $set->value . ' AS `' . $set->column . '`';
        }

        return 'SELECT ' . implode(', ', $selects);
    }

    /**
     * Build a SELECT query from INSERT ... SELECT statement.
     *
     * @param InsertStatement $statement The INSERT statement with a SELECT clause.
     * @param array<int, string> $columns The target column names.
     * @return string The SELECT SQL that produces the rows to be inserted.
     */
    private function buildInsertFromSelect(InsertStatement $statement, array $columns): string
    {
        $select = $statement->select;
        if ($select === null) {
            throw new RuntimeException('INSERT ... SELECT requires a SELECT clause.');
        }

        // Build the SELECT statement
        $selectSql = $select->build();

        // If columns are specified in INSERT, we need to wrap the SELECT to alias columns
        if ($columns !== []) {
            // Wrap the subquery and alias columns to match INSERT column list
            $aliasedColumns = [];
            foreach ($columns as $index => $column) {
                $aliasedColumns[] = sprintf('__ztd_subq.`col_%d` AS `%s`', $index, $column);
            }

            // Wrap original SELECT as subquery with numbered column aliases
            $wrappedSql = $this->wrapSelectWithNumberedAliases($selectSql, count($columns));

            return 'SELECT ' . implode(', ', $aliasedColumns) . ' FROM (' . $wrappedSql . ') AS __ztd_subq';
        }

        return $selectSql;
    }

    /**
     * Wrap a SELECT query to add numbered column aliases.
     *
     * @param string $selectSql The original SELECT SQL.
     * @param int $columnCount Expected number of columns.
     * @return string The wrapped SELECT with numbered aliases.
     */
    private function wrapSelectWithNumberedAliases(string $selectSql, int $columnCount): string
    {
        // Parse the SELECT to get its columns and rebuild with aliases
        $parser = new \PhpMyAdmin\SqlParser\Parser($selectSql);
        if (!isset($parser->statements[0]) || !$parser->statements[0] instanceof \PhpMyAdmin\SqlParser\Statements\SelectStatement) {
            throw new RuntimeException('Failed to parse INSERT ... SELECT subquery.');
        }

        $selectStmt = $parser->statements[0];
        $expressions = $selectStmt->expr;

        if (count($expressions) !== $columnCount) {
            throw new RuntimeException(sprintf(
                'INSERT column count (%d) does not match SELECT column count (%d).',
                $columnCount,
                count($expressions)
            ));
        }

        // Rebuild SELECT with numbered aliases
        $aliasedExprs = [];
        foreach ($expressions as $index => $expr) {
            // Build the expression string
            if ($expr instanceof CaseExpression) {
                $exprStr = CaseExpression::build($expr);
            } else {
                $exprStr = Expression::build($expr);
                // Remove any existing alias by extracting just the expression part
                if ($expr->alias !== null && $expr->alias !== '') {
                    // The expression already has an alias, use the original expression
                    $exprStr = $expr->expr ?? $exprStr;
                }
            }
            $aliasedExprs[] = sprintf('%s AS `col_%d`', $exprStr, $index);
        }

        // Rebuild the SELECT statement
        $newSelectClause = 'SELECT ' . implode(', ', $aliasedExprs);

        // Get the rest of the query (FROM clause onwards)
        $restOfQuery = '';
        if ($selectStmt->from !== []) {
            $fromParts = [];
            foreach ($selectStmt->from as $fromExpr) {
                $fromParts[] = Expression::build($fromExpr);
            }
            $restOfQuery .= ' FROM ' . implode(', ', $fromParts);
        }
        if ($selectStmt->where !== null && $selectStmt->where !== []) {
            $restOfQuery .= ' WHERE ' . Condition::build($selectStmt->where);
        }
        if ($selectStmt->group !== null && $selectStmt->group !== []) {
            $groupParts = [];
            foreach ($selectStmt->group as $group) {
                $groupParts[] = GroupKeyword::build($group);
            }
            $restOfQuery .= ' GROUP BY ' . implode(', ', $groupParts);
        }
        if ($selectStmt->having !== null && $selectStmt->having !== []) {
            $restOfQuery .= ' HAVING ' . Condition::build($selectStmt->having);
        }
        if ($selectStmt->order !== null && $selectStmt->order !== []) {
            $orderParts = [];
            foreach ($selectStmt->order as $order) {
                $orderParts[] = OrderKeyword::build($order);
            }
            $restOfQuery .= ' ORDER BY ' . implode(', ', $orderParts);
        }
        if ($selectStmt->limit !== null) {
            $restOfQuery .= ' LIMIT ' . Limit::build($selectStmt->limit);
        }

        return $newSelectClause . $restOfQuery;
    }
}
