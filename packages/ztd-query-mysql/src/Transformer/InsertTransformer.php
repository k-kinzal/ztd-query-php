<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Components\ArrayObj;
use PhpMyAdmin\SqlParser\Components\SetOperation;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use RuntimeException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\MySql\InsertSelectSourceExtractor;
use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\MySqlNativeUpsertProjector;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlCteShadowComposer;
use ZtdQuery\Platform\MySql\MySqlUpsertAssignmentExtractor;
use ZtdQuery\Rewrite\ShadowIdentityAllocator;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\IdentityGenerationStrategy;

/**
 * Transforms INSERT statements into SELECT queries that return the inserted rows.
 * Applies CTE shadowing via the SelectTransformer delegate.
 */
final class InsertTransformer implements SqlTransformer
{
    private MySqlParser $parser;
    private SelectTransformer $selectTransformer;
    private CastRenderer $castRenderer;
    private InsertRowRenderer $rowRenderer;
    private ShadowIdentityAllocator $identityAllocator;
    private InsertSelectRenderer $insertSelectRenderer;
    private MySqlCteShadowComposer $cteComposer;
    private MySqlNativeUpsertProjector $upsertProjector;

    public function __construct(
        MySqlParser $parser,
        SelectTransformer $selectTransformer,
        ?CastRenderer $castRenderer = null,
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->castRenderer = $castRenderer ?? new MySqlCastRenderer();
        $this->rowRenderer = new InsertRowRenderer();
        $this->identityAllocator = new ShadowIdentityAllocator();
        $this->insertSelectRenderer = new InsertSelectRenderer();
        $this->cteComposer = new MySqlCteShadowComposer();
        $this->upsertProjector = new MySqlNativeUpsertProjector();
    }

    /**
     * {@inheritDoc}
     */
    public function transform(string $sql, array $tables): string
    {
        $this->identityAllocator->beginProjection();
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

        $insertColumns = self::orderedValues($statement->into->columns ?? []);
        $tableColumns = self::orderedValues($tables[$tableName]['columns'] ?? $insertColumns);
        if ($tableColumns === []) {
            throw new UnsupportedSqlException($sql, 'Cannot determine columns');
        }

        $columnTypes = $tables[$tableName]['columnTypes'] ?? [];
        $columnDefaults = $tables[$tableName]['columnDefaults'] ?? [];
        $identityStrategies = $tables[$tableName]['identityStrategies'] ?? [];
        $existingRows = $tables[$tableName]['rows'] ?? [];
        $sourceSelectSql = (new InsertSelectSourceExtractor())->extract($sql);
        $selectSql = $this->buildInsertSelect(
            $statement,
            $tableName,
            $tableColumns,
            $insertColumns,
            $columnTypes,
            $columnDefaults,
            $identityStrategies,
            $existingRows,
            $sourceSelectSql,
        );
        $upsertExtractor = new MySqlUpsertAssignmentExtractor();
        $selectSql = $this->upsertProjector->project(
            $selectSql,
            $tableName,
            $tableColumns,
            isset($tables[$tableName]['candidateKeys']) ? $tables[$tableName]['candidateKeys'] : [],
            $upsertExtractor->extract($sql),
            incomingNamespace: $upsertExtractor->incomingAlias($sql),
        );

        return $this->selectTransformer->transform(
            $this->cteComposer->carryPrefix($sql, $selectSql),
            $tables,
        );
    }

    public function commitRewriteState(): void
    {
        $this->identityAllocator->commitProjection();
    }

    /**
     * @param list<string> $tableColumns
     * @param list<string> $insertColumns
     * @param array<string, ColumnType> $columnTypes
     * @param array<string, string> $columnDefaults
     * @param array<string, IdentityGenerationStrategy> $identityStrategies
     * @param array<int, array<string, mixed>> $existingRows
     */
    private function buildInsertSelect(
        InsertStatement $statement,
        string $tableName,
        array $tableColumns,
        array $insertColumns,
        array $columnTypes,
        array $columnDefaults,
        array $identityStrategies,
        array $existingRows,
        ?string $sourceSelectSql,
    ): string {
        if ($statement->values !== null && $statement->values !== []) {
            $rows = [];
            foreach ($statement->values as $valueSet) {
                $rows[] = $this->buildInsertRowSelect(
                    $valueSet,
                    $tableName,
                    $tableColumns,
                    $insertColumns,
                    $columnTypes,
                    $columnDefaults,
                    $identityStrategies,
                    $existingRows,
                );
            }

            return implode(' UNION ALL ', $rows);
        }

        if ($statement->set !== null && $statement->set !== []) {
            return $this->buildInsertSetSelect(
                self::orderedValues($statement->set),
                $tableName,
                $tableColumns,
                $columnTypes,
                $columnDefaults,
                $identityStrategies,
                $existingRows,
            );
        }

        if ($statement->select !== null) {
            $sourceColumns = $insertColumns !== [] ? $insertColumns : $tableColumns;
            $generatedIdentityStarts = $this->identityAllocator->allocateSelectStarts(
                $tableName,
                $identityStrategies,
                $sourceColumns,
                $existingRows,
            );

            return $this->insertSelectRenderer->render(
                $sourceSelectSql ?? $statement->select->build(),
                $tableColumns,
                $sourceColumns,
                $columnDefaults,
                $generatedIdentityStarts,
            );
        }

        throw new RuntimeException('Insert statement has no values to project.');
    }

    /**
     * @param list<string> $tableColumns
     * @param list<string> $insertColumns
     * @param array<string, ColumnType> $columnTypes
     * @param array<string, string> $columnDefaults
     * @param array<string, IdentityGenerationStrategy> $identityStrategies
     * @param array<int, array<string, mixed>> $existingRows
     */
    private function buildInsertRowSelect(
        ArrayObj $valueSet,
        string $tableName,
        array $tableColumns,
        array $insertColumns,
        array $columnTypes,
        array $columnDefaults,
        array $identityStrategies,
        array $existingRows,
    ): string {
        $rawValues = self::orderedValues($valueSet->raw !== [] ? $valueSet->raw : $valueSet->values);
        $parsedValues = self::orderedValues($valueSet->values);
        $values = [];
        foreach ($rawValues as $index => $rawValue) {
            $parsedValue = $parsedValues[$index] ?? $rawValue;
            $values[] = strcasecmp($parsedValue, 'DEFAULT') === 0 ? $parsedValue : $rawValue;
        }
        $sourceColumns = $insertColumns !== [] || $values === [] ? $insertColumns : $tableColumns;
        try {
            $providedExpressions = $this->rowRenderer->providedExpressions($sourceColumns, $values);
        } catch (\InvalidArgumentException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }
        $generatedValues = $this->identityAllocator->allocateMissing(
            $tableName,
            $identityStrategies,
            array_keys($providedExpressions),
            $existingRows,
        );
        $projected = $this->rowRenderer->render($tableColumns, $providedExpressions, $columnDefaults, $generatedValues);

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
     * @param array<string, IdentityGenerationStrategy> $identityStrategies
     * @param array<int, array<string, mixed>> $existingRows
     */
    private function buildInsertSetSelect(
        array $setOperations,
        string $tableName,
        array $tableColumns,
        array $columnTypes,
        array $columnDefaults,
        array $identityStrategies,
        array $existingRows,
    ): string {
        $columns = [];
        $values = [];
        foreach ($setOperations as $set) {
            $columns[] = $set->column;
            $values[] = $set->value;
        }
        $providedExpressions = $this->rowRenderer->providedExpressions($columns, $values);
        $generatedValues = $this->identityAllocator->allocateMissing(
            $tableName,
            $identityStrategies,
            array_keys($providedExpressions),
            $existingRows,
        );
        $projected = $this->rowRenderer->render($tableColumns, $providedExpressions, $columnDefaults, $generatedValues);
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
     * @template T
     * @param array<array-key, T> $values
     * @return list<T>
     */
    private static function orderedValues(array $values): array
    {
        $ordered = [];
        foreach ($values as $value) {
            $ordered[] = $value;
        }

        return $ordered;
    }

}
