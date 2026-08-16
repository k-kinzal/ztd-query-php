<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Transformer;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\Postgres\PgSqlCastRenderer;
use ZtdQuery\Platform\Postgres\PgSqlParser;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * Transforms INSERT statements into SELECT queries that return the inserted rows.
 * Applies CTE shadowing via the SelectTransformer delegate.
 */
final class InsertTransformer implements SqlTransformer
{
    private PgSqlParser $parser;
    private SelectTransformer $selectTransformer;
    private CastRenderer $castRenderer;

    public function __construct(
        PgSqlParser $parser,
        SelectTransformer $selectTransformer,
        ?CastRenderer $castRenderer = null,
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->castRenderer = $castRenderer ?? new PgSqlCastRenderer();
    }

    /**
     * {@inheritDoc}
     */
    public function transform(string $sql, array $tables): string
    {
        $tableName = $this->parser->extractInsertTable($sql);
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

        if ($this->parser->hasInsertSelect($sql)) {
            $selectSql = $this->parser->extractInsertSelectSql($sql);
            if ($selectSql === null) {
                throw new UnsupportedSqlException($sql, 'Cannot extract INSERT ... SELECT subquery');
            }

            return $this->selectTransformer->transform($selectSql, $tables);
        }

        $valueRows = $this->parser->extractInsertValues($sql);
        if ($valueRows === []) {
            throw new UnsupportedSqlException($sql, 'Cannot extract INSERT values');
        }

        $selectParts = [];
        $columnTypes = $tables[$tableName]['columnTypes'] ?? [];
        foreach ($valueRows as $values) {
            if (count($values) !== count($columns)) {
                throw new UnsupportedSqlException($sql, 'Insert values count does not match column count');
            }

            $selects = [];
            foreach ($columns as $index => $column) {
                $expr = trim($values[$index]);
                $type = $columnTypes[$column] ?? null;
                if ($type instanceof ColumnType) {
                    $expr = $this->castInsertExpression($expr, $type);
                }
                $selects[] = $expr . ' AS "' . $column . '"';
            }
            $selectParts[] = 'SELECT ' . implode(', ', $selects);
        }

        $selectSql = implode(' UNION ALL ', $selectParts);

        return $this->selectTransformer->transform($selectSql, $tables);
    }

    private function castInsertExpression(string $expression, ColumnType $type): string
    {
        if ($type->family === ColumnTypeFamily::BOOLEAN && $expression === '?') {
            return "CAST(COALESCE(NULLIF(CAST(? AS TEXT), ''), 'false') AS BOOLEAN)";
        }

        return $this->castRenderer->renderCast($expression, $type);
    }
}
