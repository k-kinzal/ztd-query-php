<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Components\ArrayObj;
use PhpMyAdmin\SqlParser\Components\CaseExpression;
use PhpMyAdmin\SqlParser\Components\Condition;
use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Components\GroupKeyword;
use PhpMyAdmin\SqlParser\Components\Limit;
use PhpMyAdmin\SqlParser\Components\OrderKeyword;
use PhpMyAdmin\SqlParser\Components\SetOperation;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement as PhpMyAdminSelectStatement;
use RuntimeException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Rewrite\InsertRowProjector;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\ColumnType;

/**
 * Transforms INSERT statements into SELECT queries that return the inserted rows.
 * Applies CTE shadowing via the SelectTransformer delegate.
 */
final class InsertTransformer implements SqlTransformer
{
    private MySqlParser $parser;
    private SelectTransformer $selectTransformer;
    private CastRenderer $castRenderer;
    private InsertRowProjector $rowProjector;

    public function __construct(
        MySqlParser $parser,
        SelectTransformer $selectTransformer,
        ?CastRenderer $castRenderer = null,
        ?InsertRowProjector $rowProjector = null,
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->castRenderer = $castRenderer ?? new MySqlCastRenderer();
        $this->rowProjector = $rowProjector ?? new InsertRowProjector();
    }

    /**
     * {@inheritDoc}
     */
    public function transform(string $sql, array $tables): string
    {
        $statements = $this->parser->parse($sql);
        if (!isset($statements[0]) || !$statements[0] instanceof InsertStatement) {
            throw new UnsupportedSqlException($sql, 'Expected INSERT statement');
        }

        $statement = $statements[0];

        if ($statement->into === null || $statement->into->dest === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve INSERT target');
        }

        $dest = $statement->into->dest;
        $tableName = is_string($dest) ? $dest : ($dest->table ?? null);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $insertColumns = array_values(array_filter($statement->into->columns ?? [], 'is_string'));
        $tableColumns = array_values($tables[$tableName]['columns'] ?? $insertColumns);
        if ($tableColumns === []) {
            throw new UnsupportedSqlException($sql, 'Cannot determine columns');
        }

        $columnTypes = $tables[$tableName]['columnTypes'] ?? [];
        $columnDefaults = $tables[$tableName]['columnDefaults'] ?? [];
        $selectSql = $this->buildInsertSelect($statement, $tableColumns, $insertColumns, $columnTypes, $columnDefaults);

        return $this->selectTransformer->transform($selectSql, $tables);
    }

    /**
     * @param list<string> $tableColumns
     * @param list<string> $insertColumns
     * @param array<string, ColumnType> $columnTypes
     * @param array<string, string> $columnDefaults
     */
    private function buildInsertSelect(
        InsertStatement $statement,
        array $tableColumns,
        array $insertColumns,
        array $columnTypes,
        array $columnDefaults,
    ): string {
        if ($statement->values !== null && $statement->values !== []) {
            $rows = [];
            foreach ($statement->values as $valueSet) {
                $rows[] = $this->buildInsertRowSelect($valueSet, $tableColumns, $insertColumns, $columnTypes, $columnDefaults);
            }

            return implode(' UNION ALL ', $rows);
        }

        if ($statement->set !== null && $statement->set !== []) {
            return $this->buildInsertSetSelect(array_values($statement->set), $tableColumns, $columnTypes, $columnDefaults);
        }

        if ($statement->select !== null) {
            return $this->buildInsertFromSelect($statement, $insertColumns !== [] ? $insertColumns : $tableColumns);
        }

        throw new RuntimeException('Insert statement has no values to project.');
    }

    /**
     * @param list<string> $tableColumns
     * @param list<string> $insertColumns
     * @param array<string, ColumnType> $columnTypes
     * @param array<string, string> $columnDefaults
     */
    private function buildInsertRowSelect(
        ArrayObj $valueSet,
        array $tableColumns,
        array $insertColumns,
        array $columnTypes,
        array $columnDefaults,
    ): string {
        $values = array_values($valueSet->raw !== [] ? $valueSet->raw : $valueSet->values);
        $sourceColumns = $insertColumns !== [] || $values === [] ? $insertColumns : $tableColumns;
        try {
            $projected = $this->rowProjector->project($tableColumns, $sourceColumns, $values, $columnDefaults);
        } catch (\InvalidArgumentException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        $selects = [];
        foreach ($projected as $column => $expr) {
            $type = $columnTypes[$column] ?? null;
            if ($type instanceof ColumnType) {
                $expr = $this->castRenderer->renderCast($expr, $type);
            }
            $selects[] = $expr . ' AS `' . $column . '`';
        }

        return 'SELECT ' . implode(', ', $selects);
    }

    /**
     * @param array<int, SetOperation> $setOperations
     * @param list<string> $tableColumns
     * @param array<string, ColumnType> $columnTypes
     * @param array<string, string> $columnDefaults
     */
    private function buildInsertSetSelect(
        array $setOperations,
        array $tableColumns,
        array $columnTypes,
        array $columnDefaults,
    ): string {
        $columns = [];
        $values = [];
        foreach ($setOperations as $set) {
            $columns[] = $set->column;
            $values[] = $set->value;
        }
        $projected = $this->rowProjector->project($tableColumns, $columns, $values, $columnDefaults);
        $selects = [];
        foreach ($projected as $column => $expression) {
            $type = $columnTypes[$column] ?? null;
            if ($type instanceof ColumnType) {
                $expression = $this->castRenderer->renderCast($expression, $type);
            }
            $selects[] = $expression . ' AS `' . $column . '`';
        }

        return 'SELECT ' . implode(', ', $selects);
    }

    /**
     * @param array<int, string> $columns
     */
    private function buildInsertFromSelect(InsertStatement $statement, array $columns): string
    {
        $select = $statement->select;
        if ($select === null) {
            throw new RuntimeException('INSERT ... SELECT requires a SELECT clause.');
        }

        $selectSql = $select->build();

        if ($columns !== []) {
            $aliasedColumns = [];
            foreach ($columns as $index => $column) {
                $aliasedColumns[] = sprintf('__ztd_subq.`col_%d` AS `%s`', $index, $column);
            }

            $wrappedSql = $this->wrapSelectWithNumberedAliases($selectSql, count($columns));

            return 'SELECT ' . implode(', ', $aliasedColumns) . ' FROM (' . $wrappedSql . ') AS __ztd_subq';
        }

        return $selectSql;
    }

    private function wrapSelectWithNumberedAliases(string $selectSql, int $columnCount): string
    {
        $statements = $this->parser->parse($selectSql);
        if (!isset($statements[0]) || !$statements[0] instanceof PhpMyAdminSelectStatement) {
            throw new RuntimeException('Failed to parse INSERT ... SELECT subquery.');
        }

        $selectStmt = $statements[0];
        $expressions = $selectStmt->expr;

        if (count($expressions) !== $columnCount) {
            throw new RuntimeException(sprintf(
                'INSERT column count (%d) does not match SELECT column count (%d).',
                $columnCount,
                count($expressions)
            ));
        }

        $aliasedExprs = [];
        foreach ($expressions as $index => $expr) {
            if ($expr instanceof CaseExpression) {
                $exprStr = CaseExpression::build($expr);
            } else {
                $exprStr = Expression::build($expr);
                if ($expr->alias !== null && $expr->alias !== '') {
                    $exprStr = $expr->expr ?? $exprStr;
                }
            }
            $aliasedExprs[] = sprintf('%s AS `col_%d`', $exprStr, $index);
        }

        $newSelectClause = 'SELECT ' . implode(', ', $aliasedExprs);

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
