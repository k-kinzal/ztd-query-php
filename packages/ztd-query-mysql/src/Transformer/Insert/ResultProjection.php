<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer\Insert;

use PhpMyAdmin\SqlParser\Components\ArrayObj;
use PhpMyAdmin\SqlParser\Components\SetOperation;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use RuntimeException;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\MySql\Transformer\InsertRowRenderer;
use ZtdQuery\Platform\MySql\Transformer\InsertSelectRenderer;
use ZtdQuery\Rewrite\ShadowIdentityAllocator;
use ZtdQuery\Schema\ColumnType;

/**
 * Result Projection.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ResultProjection
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private CastRenderer $castRenderer, private ShadowIdentityAllocator $identityAllocator, private InsertSelectRenderer $insertSelectRenderer, private InsertRowRenderer $rowRenderer)
    {
    }
    /**

     * @throws RuntimeException
     */
    public function buildInsertSelect(
        InsertStatement $statement,
        InsertTarget $target,
        ?string $sourceSelectSql,
    ): string {
        if ($statement->values !== null && $statement->values !== []) {
            $rows = [];
            foreach ($statement->values as $valueSet) {
                $rows[] = $this->buildInsertRowSelect(
                    $valueSet,
                    $target,
                );
            }

            return implode(' UNION ALL ', $rows);
        }

        if ($statement->set !== null && $statement->set !== []) {
            return $this->buildInsertSetSelect(
                self::orderedValues($statement->set),
                $target,
            );
        }

        if ($statement->select !== null) {
            $sourceColumns = $target->insertColumns !== [] ? $target->insertColumns : $target->tableColumns;
            $generatedIdentityStarts = $this->identityAllocator->allocateSelectStarts(
                $target->tableName,
                $target->identityStrategies,
                $sourceColumns,
                $target->existingRows,
            );

            return $this->insertSelectRenderer->render(
                $sourceSelectSql ?? $statement->select->build(),
                $target->tableColumns,
                $sourceColumns,
                $target->columnDefaults,
                $generatedIdentityStarts,
            );
        }

        throw new RuntimeException('Insert statement has no values to project.');
    }

    /**

     * @throws RuntimeException
     */
    public function buildInsertRowSelect(
        ArrayObj $valueSet,
        InsertTarget $target,
    ): string {
        $rawValues = self::orderedValues($valueSet->raw !== [] ? $valueSet->raw : $valueSet->values);
        $parsedValues = self::orderedValues($valueSet->values);
        $values = [];
        foreach ($rawValues as $index => $rawValue) {
            $parsedValue = $parsedValues[$index] ?? $rawValue;
            $values[] = strcasecmp($parsedValue, 'DEFAULT') === 0 ? $parsedValue : $rawValue;
        }
        $sourceColumns = $target->insertColumns !== [] || $values === [] ? $target->insertColumns : $target->tableColumns;
        if (count($sourceColumns) !== count($values)) {
            throw new RuntimeException('Insert values count does not match column count.');
        }
        $providedExpressions = $this->rowRenderer->providedExpressions($sourceColumns, $values);
        $generatedValues = $this->identityAllocator->allocateMissing(
            $target->tableName,
            $target->identityStrategies,
            array_keys($providedExpressions),
            $target->existingRows,
        );
        $projected = $this->rowRenderer->render($target->tableColumns, $providedExpressions, $target->columnDefaults, $generatedValues);

        $selects = [];
        foreach ($projected as $column => $expr) {
            $type = $target->columnTypes[$column] ?? null;
            if ($type instanceof ColumnType) {
                $expr = $this->castRenderer->renderCast($expr, $type);
            }
            $selects[] = $expr . ' AS `' . $column . '`';
        }

        return 'SELECT ' . implode(', ', $selects);
    }

    /**
     * @param array<int, SetOperation> $setOperations

     */
    public function buildInsertSetSelect(
        array $setOperations,
        InsertTarget $target,
    ): string {
        $columns = [];
        $values = [];
        foreach ($setOperations as $set) {
            $columns[] = $set->column;
            $values[] = $set->value;
        }
        $providedExpressions = $this->rowRenderer->providedExpressions($columns, $values);
        $generatedValues = $this->identityAllocator->allocateMissing(
            $target->tableName,
            $target->identityStrategies,
            array_keys($providedExpressions),
            $target->existingRows,
        );
        $projected = $this->rowRenderer->render($target->tableColumns, $providedExpressions, $target->columnDefaults, $generatedValues);
        $selects = [];
        foreach ($projected as $column => $expression) {
            $type = $target->columnTypes[$column] ?? null;
            if ($type instanceof ColumnType) {
                $expression = $this->castRenderer->renderCast($expression, $type);
            }
            $selects[] = $expression . ' AS `' . $column . '`';
        }

        return 'SELECT ' . implode(', ', $selects);
    }

    /**
     * @template T
     * @param array<array-key, T> $values
     * @return list<T>
     */
    public static function orderedValues(array $values): array
    {
        $ordered = [];
        foreach ($values as $value) {
            $ordered[] = $value;
        }

        return $ordered;
    }
}
