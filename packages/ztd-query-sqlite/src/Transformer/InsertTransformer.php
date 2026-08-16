<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite\Transformer;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\Sqlite\SqliteCastRenderer;
use ZtdQuery\Platform\Sqlite\SqliteParser;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\ColumnType;

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

    public function __construct(
        SqliteParser $parser,
        SelectTransformer $selectTransformer,
        ?CastRenderer $castRenderer = null,
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->castRenderer = $castRenderer ?? new SqliteCastRenderer();
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

        $columns = $this->parser->extractInsertColumns($sql);
        if ($columns === [] && isset($tables[$tableName])) {
            $columns = $tables[$tableName]['columns'];
        }
        if ($columns === []) {
            throw new UnsupportedSqlException($sql, 'Cannot determine columns');
        }

        $columnTypes = $tables[$tableName]['columnTypes'] ?? [];
        $selectSql = $this->buildInsertSelect($sql, $columns, $columnTypes);

        return $this->selectTransformer->transform($selectSql, $tables);
    }

    /**
     * @param array<int, string> $columns
     * @param array<string, ColumnType> $columnTypes
     */
    private function buildInsertSelect(string $sql, array $columns, array $columnTypes): string
    {
        if ($this->parser->hasInsertSelect($sql)) {
            $selectSql = $this->parser->extractInsertSelect($sql);
            if ($selectSql === null) {
                throw new \RuntimeException('Failed to extract SELECT from INSERT ... SELECT.');
            }

            return $selectSql;
        }

        $valueSets = $this->parser->extractInsertValues($sql);
        if ($valueSets !== []) {
            $rows = [];
            foreach ($valueSets as $values) {
                $rows[] = $this->buildInsertRowSelect($values, $columns, $columnTypes);
            }

            return implode(' UNION ALL ', $rows);
        }

        throw new \RuntimeException('Insert statement has no values to project.');
    }

    /**
     * @param array<int, string> $values
     * @param array<int, string> $columns
     * @param array<string, ColumnType> $columnTypes
     */
    private function buildInsertRowSelect(array $values, array $columns, array $columnTypes): string
    {
        if (count($values) !== count($columns)) {
            throw new \RuntimeException('Insert values count does not match column count.');
        }

        $selects = [];
        foreach ($columns as $index => $column) {
            $expr = trim($values[$index]);
            $type = $columnTypes[$column] ?? null;
            if ($type instanceof ColumnType) {
                $expr = $this->castRenderer->renderCast($expr, $type);
            }
            $selects[] = $expr . ' AS "' . $column . '"';
        }

        return 'SELECT ' . implode(', ', $selects);
    }
}
