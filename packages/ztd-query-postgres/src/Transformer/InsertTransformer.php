<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Transformer;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\Postgres\PgSqlCastRenderer;
use ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter;
use ZtdQuery\Platform\Postgres\PgSqlParser;
use ZtdQuery\Rewrite\InsertRowProjector;
use ZtdQuery\Rewrite\InsertSelectProjector;
use ZtdQuery\Rewrite\ShadowIdentityAllocator;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Transforms INSERT statements into SELECT queries that return the inserted rows.
 * Applies CTE shadowing via the SelectTransformer delegate.
 */
final class InsertTransformer implements SqlTransformer
{
    private PgSqlParser $parser;
    private SelectTransformer $selectTransformer;
    private CastRenderer $castRenderer;
    private InsertRowProjector $rowProjector;
    private ShadowIdentityAllocator $identityAllocator;
    private InsertSelectProjector $insertSelectProjector;

    public function __construct(
        PgSqlParser $parser,
        SelectTransformer $selectTransformer,
        ?CastRenderer $castRenderer = null,
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->castRenderer = $castRenderer ?? new PgSqlCastRenderer();
        $this->rowProjector = new InsertRowProjector();
        $this->identityAllocator = new ShadowIdentityAllocator();
        $this->insertSelectProjector = new InsertSelectProjector(new PgSqlIdentifierQuoter());
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

        $insertColumns = $this->parser->extractInsertColumns($sql);
        $tableColumns = self::orderedValues($tables[$tableName]['columns'] ?? $insertColumns);
        if ($tableColumns === []) {
            throw new UnsupportedSqlException($sql, 'Cannot determine columns');
        }

        $columnDefaults = $tables[$tableName]['columnDefaults'] ?? [];
        $identityStrategies = $tables[$tableName]['identityStrategies'] ?? [];
        $existingRows = $tables[$tableName]['rows'] ?? [];

        if ($this->parser->hasInsertSelect($sql)) {
            $selectSql = $this->parser->extractInsertSelectSql($sql);
            if ($selectSql === null) {
                throw new UnsupportedSqlException($sql, 'Cannot extract INSERT ... SELECT subquery');
            }

            $sourceColumns = $insertColumns !== [] ? $insertColumns : $tableColumns;
            $generatedValues = $this->identityAllocator->allocateSelectExpressions(
                $tableName,
                array_diff_key($identityStrategies, array_flip($sourceColumns)),
                $existingRows,
            );
            $projectedSql = $this->insertSelectProjector->project(
                $selectSql,
                $tableColumns,
                $sourceColumns,
                $columnDefaults,
                $generatedValues,
            );

            return $this->selectTransformer->transform($projectedSql, $tables);
        }

        $valueRows = SqlTokenStream::tokenize($sql)->topLevelClause(['DEFAULT', 'VALUES']) !== null
            ? [[]]
            : $this->parser->extractInsertValues($sql);
        if ($valueRows === []) {
            throw new UnsupportedSqlException($sql, 'Cannot extract INSERT values');
        }

        $selectParts = [];
        $columnTypes = $tables[$tableName]['columnTypes'] ?? [];
        foreach ($valueRows as $values) {
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
            } catch (\InvalidArgumentException) {
                throw new UnsupportedSqlException($sql, 'Insert values count does not match column count');
            }

            $selects = [];
            foreach ($projected as $column => $expr) {
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

    private function castInsertExpression(string $expression, ColumnType $type): string
    {
        if ($type->family === ColumnTypeFamily::BOOLEAN && $expression === '?') {
            return "CAST(COALESCE(NULLIF(CAST(? AS TEXT), ''), 'false') AS BOOLEAN)";
        }

        return $this->castRenderer->renderCast($expression, $type);
    }
}
