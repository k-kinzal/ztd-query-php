<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite\Transformer;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\Sqlite\SqliteCastRenderer;
use ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter;
use ZtdQuery\Platform\Sqlite\SqliteParser;
use ZtdQuery\Rewrite\InsertRowProjector;
use ZtdQuery\Rewrite\InsertSelectProjector;
use ZtdQuery\Rewrite\ShadowIdentityAllocator;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Transforms INSERT/REPLACE statements into SELECT queries that return the inserted rows.
 * Applies CTE shadowing via the SelectTransformer delegate.
 *
 * Handles:
 * - INSERT INTO ... VALUES (...)
 * - INSERT OR REPLACE INTO ... VALUES (...)
 * - REPLACE INTO ... VALUES (...)
 * - INSERT INTO ... SELECT ...
 * - INSERT INTO ... ON CONFLICT ... DO UPDATE SET ...
 */
final class InsertTransformer implements SqlTransformer
{
    private SqliteParser $parser;
    private SelectTransformer $selectTransformer;
    private CastRenderer $castRenderer;
    private InsertRowProjector $rowProjector;
    private ShadowIdentityAllocator $identityAllocator;
    private InsertSelectProjector $insertSelectProjector;

    public function __construct(
        SqliteParser $parser,
        SelectTransformer $selectTransformer,
        ?CastRenderer $castRenderer = null,
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->castRenderer = $castRenderer ?? new SqliteCastRenderer();
        $this->rowProjector = new InsertRowProjector();
        $this->identityAllocator = new ShadowIdentityAllocator();
        $this->insertSelectProjector = new InsertSelectProjector(new SqliteIdentifierQuoter());
    }

    /**
     * {@inheritDoc}
     */
    public function transform(string $sql, array $tables): string
    {
        $type = $this->parser->classifyStatement($sql);
        if ($type !== 'INSERT') {
            throw new UnsupportedSqlException($sql, 'Expected INSERT/REPLACE statement');
        }

        $tableName = $this->parser->extractTargetTable($sql);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve INSERT target');
        }

        $insertColumns = self::orderedValues($this->parser->extractInsertColumns($sql));
        $tableColumns = self::orderedValues($tables[$tableName]['columns'] ?? $insertColumns);
        if ($tableColumns === []) {
            throw new UnsupportedSqlException($sql, 'Cannot determine columns');
        }

        $columnTypes = $tables[$tableName]['columnTypes'] ?? [];
        $columnDefaults = $tables[$tableName]['columnDefaults'] ?? [];
        $identityStrategies = $tables[$tableName]['identityStrategies'] ?? [];
        $existingRows = $tables[$tableName]['rows'] ?? [];
        $selectSql = $this->buildInsertSelect(
            $sql,
            $tableName,
            $tableColumns,
            $insertColumns,
            $columnTypes,
            $columnDefaults,
            $identityStrategies,
            $existingRows,
        );

        return $this->selectTransformer->transform($selectSql, $tables);
    }

    /**
     * @param list<string> $tableColumns
     * @param list<string> $insertColumns
     * @param array<string, ColumnType> $columnTypes
     * @param array<string, string> $columnDefaults
     * @param array<string, \ZtdQuery\Schema\IdentityGenerationStrategy> $identityStrategies
     * @param array<int, array<string, mixed>> $existingRows
     */
    private function buildInsertSelect(
        string $sql,
        string $tableName,
        array $tableColumns,
        array $insertColumns,
        array $columnTypes,
        array $columnDefaults,
        array $identityStrategies,
        array $existingRows,
    ): string {
        if ($this->parser->hasInsertSelect($sql)) {
            $selectSql = $this->parser->extractInsertSelect($sql);
            if ($selectSql === null) {
                throw new \RuntimeException('Failed to extract SELECT from INSERT ... SELECT.');
            }

            $sourceColumns = $insertColumns !== [] ? $insertColumns : $tableColumns;
            $generatedValues = $this->identityAllocator->allocateSelectExpressions(
                $tableName,
                array_diff_key($identityStrategies, array_flip($sourceColumns)),
                $existingRows,
            );

            return $this->insertSelectProjector->project(
                $selectSql,
                $tableColumns,
                $sourceColumns,
                $columnDefaults,
                $generatedValues,
            );
        }

        $valueSets = SqlTokenStream::tokenize($sql)->topLevelClause(['DEFAULT', 'VALUES']) !== null
            ? [[]]
            : $this->parser->extractInsertValues($sql);
        if ($valueSets !== []) {
            $rows = [];
            foreach ($valueSets as $values) {
                $rows[] = $this->buildInsertRowSelect(
                    $values,
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

        throw new \RuntimeException('Insert statement has no values to project.');
    }

    /**
     * @param array<int, string> $values
     * @param list<string> $tableColumns
     * @param list<string> $insertColumns
     * @param array<string, ColumnType> $columnTypes
     * @param array<string, string> $columnDefaults
     * @param array<string, \ZtdQuery\Schema\IdentityGenerationStrategy> $identityStrategies
     * @param array<int, array<string, mixed>> $existingRows
     */
    private function buildInsertRowSelect(
        array $values,
        string $tableName,
        array $tableColumns,
        array $insertColumns,
        array $columnTypes,
        array $columnDefaults,
        array $identityStrategies,
        array $existingRows,
    ): string {
        $values = self::orderedValues($values);
        $sourceColumns = $insertColumns !== [] || $values === [] ? $insertColumns : $tableColumns;
        $generatedValues = $this->identityAllocator->allocateMissing(
            $tableName,
            $identityStrategies,
            $sourceColumns,
            $values,
            $existingRows,
        );
        try {
            $projected = $this->rowProjector->project($tableColumns, $sourceColumns, $values, $columnDefaults, $generatedValues);
        } catch (\InvalidArgumentException $exception) {
            throw new \RuntimeException($exception->getMessage(), 0, $exception);
        }

        $selects = [];
        foreach ($projected as $column => $expr) {
            $type = $columnTypes[$column] ?? null;
            if ($type instanceof ColumnType) {
                $expr = $this->castRenderer->renderCast($expr, $type);
            }
            $selects[] = $expr . ' AS "' . $column . '"';
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
