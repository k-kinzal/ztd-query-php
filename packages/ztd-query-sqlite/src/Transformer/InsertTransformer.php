<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite\Transformer;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\Sqlite\SqliteCastRenderer;
use ZtdQuery\Platform\Sqlite\SqliteParser;
use ZtdQuery\Rewrite\InsertRowProjector;
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

    public function __construct(
        SqliteParser $parser,
        SelectTransformer $selectTransformer,
        ?CastRenderer $castRenderer = null,
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->castRenderer = $castRenderer ?? new SqliteCastRenderer();
        $this->rowProjector = new InsertRowProjector();
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
        $selectSql = $this->buildInsertSelect($sql, $tableColumns, $insertColumns, $columnTypes, $columnDefaults);

        return $this->selectTransformer->transform($selectSql, $tables);
    }

    /**
     * @param list<string> $tableColumns
     * @param list<string> $insertColumns
     * @param array<string, ColumnType> $columnTypes
     * @param array<string, string> $columnDefaults
     */
    private function buildInsertSelect(
        string $sql,
        array $tableColumns,
        array $insertColumns,
        array $columnTypes,
        array $columnDefaults,
    ): string {
        if ($this->parser->hasInsertSelect($sql)) {
            $selectSql = $this->parser->extractInsertSelect($sql);
            if ($selectSql === null) {
                throw new \RuntimeException('Failed to extract SELECT from INSERT ... SELECT.');
            }

            return $selectSql;
        }

        $valueSets = SqlTokenStream::tokenize($sql)->topLevelClause(['DEFAULT', 'VALUES']) !== null
            ? [[]]
            : $this->parser->extractInsertValues($sql);
        if ($valueSets !== []) {
            $rows = [];
            foreach ($valueSets as $values) {
                $rows[] = $this->buildInsertRowSelect($values, $tableColumns, $insertColumns, $columnTypes, $columnDefaults);
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
     */
    private function buildInsertRowSelect(
        array $values,
        array $tableColumns,
        array $insertColumns,
        array $columnTypes,
        array $columnDefaults,
    ): string {
        $values = self::orderedValues($values);
        $sourceColumns = $insertColumns !== [] || $values === [] ? $insertColumns : $tableColumns;
        try {
            $projected = $this->rowProjector->project($tableColumns, $sourceColumns, $values, $columnDefaults);
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
