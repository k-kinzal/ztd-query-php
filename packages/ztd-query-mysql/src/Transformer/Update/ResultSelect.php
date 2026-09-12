<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer\Update;

use PhpMyAdmin\SqlParser\Components\Condition;
use PhpMyAdmin\SqlParser\Components\Limit;
use PhpMyAdmin\SqlParser\Components\OrderKeyword;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use RuntimeException;
use ZtdQuery\Shadow\Mutation\MultiTableMutationTarget;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;

/**
 * Result Select.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ResultSelect
{
    /**
     * Build a result-select SQL from an UPDATE statement.
     *
     * @param UpdateStatement $stmt
     * @param array<int, string> $columns
     * @param array<int, string> $primaryKeys
     * @param list<MultiTableMutationTarget> $targets
     * @param list<string> $assignmentValues
     * @return array{sql: string, table: string, tables: array<string, array{alias: string}>}
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

        $alias = $targetTableExpr->alias;
        $qualifier = $alias !== null && $alias !== '' ? $alias : $targetTableName;

        $allTargetTables = $this->targetTables($stmt);

        $selectCols = $this->selectColumns($stmt, $columns, $primaryKeys, $qualifier, $assignmentValues);

        if ($targets !== []) {
            $selectCols = (new TargetProjection())->multiTableSelectColumns($stmt, $allTargetTables, $targets, $assignmentValues);
        }

        $sql = $this->buildQuery($stmt, $selectCols, $targetTableExpr, $targetTableName, $qualifier, $whereExpression, $sourceExpression);

        return ['sql' => $sql, 'table' => $targetTableName, 'tables' => $allTargetTables];
    }
    /**
     * @return array<string, array{alias: string}>
     */
    public function targetTables(UpdateStatement $stmt): array
    {
        /**
         * @var array<string, array{alias: string}> $allTargetTables
         */
        $allTargetTables = [];
        foreach ($stmt->tables ?? [] as $tableExpr) {
            $tableName = (($tableExpr->table ?? '') !== '' ? $tableExpr->table : $tableExpr->expr ?? null) ?? '';
            $alias = $tableExpr->alias ?? $tableName;
            if ($tableName !== '') {
                $allTargetTables[$tableName] = ['alias' => $alias];
            }
        }

        return $allTargetTables;
    }

    /**
     * @param array<int, string> $columns
     * @param array<int, string> $primaryKeys
     * @param list<string> $assignmentValues
     * @return list<string>
     */
    public function selectColumns(UpdateStatement $stmt, array $columns, array $primaryKeys, string $qualifier, array $assignmentValues): array
    {
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

        return $selectCols;
    }

    /**
     * @param list<string> $selectCols
     */
    public function buildQuery(UpdateStatement $stmt, array $selectCols, \PhpMyAdmin\SqlParser\Components\Expression $targetTableExpr, string $targetTableName, string $qualifier, ?string $whereExpression, ?string $sourceExpression): string
    {
        if ($selectCols === []) {
            $selectCols[] = '*';
        }
        $selectList = implode(', ', $selectCols);

        $aliasClause = '';
        if (($targetTableExpr->alias ?? '') !== '') {
            $aliasClause = ' AS ' . $targetTableExpr->alias;
        }

        $additionalTables = (new TargetProjection())->buildAdditionalTables($stmt);

        $joinClause = (new TargetProjection())->buildJoinClause($stmt);

        $whereClause = '';
        if ($whereExpression === null) {
            $whereExpression = Condition::build($stmt->where ?? []);
        }
        if ($whereExpression !== '') {
            $whereClause = ' WHERE ' . $whereExpression;
        }

        $orderByClause = '';
        if ($stmt->order !== null && $stmt->order !== []) {
            $orderParts = [];
            foreach ($stmt->order as $orderExpr) {
                $orderParts[] = OrderKeyword::build($orderExpr);
            }
            $orderByClause = ' ORDER BY ' . implode(', ', $orderParts);
        }

        $limitClause = '';
        if ($stmt->limit !== null) {
            $limitClause = ' LIMIT ' . Limit::build($stmt->limit);
        }

        $sourceClause = $sourceExpression ?? "`$targetTableName`$aliasClause$additionalTables$joinClause";
        if ($additionalTables === '' && $joinClause === '' && ($orderByClause !== '' || $limitClause !== '')) {
            $selectedRows = "SELECT * FROM $sourceClause$whereClause$orderByClause$limitClause";
            $sql = "SELECT $selectList FROM ($selectedRows) AS `$qualifier`";
        } else {
            $sql = "SELECT $selectList FROM $sourceClause$whereClause$orderByClause$limitClause";
        }

        return $sql;
    }

}
