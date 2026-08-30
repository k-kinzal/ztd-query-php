<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Transformer;

use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlCastRenderer;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Platform\Postgres\Parse\PgSqlParser;
use ZtdQuery\Platform\Postgres\Parse\PgSqlWithPrefix;
use ZtdQuery\Platform\Postgres\Rewrite\PgSqlNativeUpsertProjector;
use ZtdQuery\Platform\ValueRenderer;
use ZtdQuery\Rewrite\ShadowIdentityAllocator;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Schema\Key\CandidateKeySet;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Transforms INSERT statements into SELECT queries that return the inserted rows.
 * Applies CTE shadowing via the SelectTransformer delegate.
 *
 * @phpstan-import-type RenderableValue from ValueRenderer
 * @phpstan-import-type ShadowTables from SqlTransformer
 */
final class InsertTransformer implements SqlTransformer
{
    private PgSqlParser $parser;
    private SelectTransformer $selectTransformer;
    private CastRenderer $castRenderer;
    private InsertRowRenderer $rowRenderer;
    private ShadowIdentityAllocator $identityAllocator;
    private InsertSelectRenderer $insertSelectRenderer;

    /** @readonly */
    private PgSqlWithPrefix $withPrefix;
    private PgSqlNativeUpsertProjector $upsertProjector;

    /**
     * Binds the instance to what it will work from.
     *
     * @param PgSqlParser $parser
     * @param SelectTransformer $selectTransformer
     * @param ?CastRenderer $castRenderer
     */
    public function __construct(
        PgSqlParser $parser,
        SelectTransformer $selectTransformer,
        ?CastRenderer $castRenderer = null,
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->castRenderer = $castRenderer ?? new PgSqlCastRenderer();
        $this->rowRenderer = new InsertRowRenderer();
        $this->identityAllocator = new ShadowIdentityAllocator();
        $this->insertSelectRenderer = new InsertSelectRenderer();
        $this->withPrefix = new PgSqlWithPrefix();
        $this->upsertProjector = new PgSqlNativeUpsertProjector();
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedSqlException
     */
    public function transform(string $sql, array $tables): string
    {
        $this->identityAllocator->beginProjection();
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
        $identityTable = $tables[$tableName]['storageTable'] ?? $tableName;

        if ($this->parser->hasInsertSelect($sql)) {
            $selectSql = $this->parser->extractInsertSelectSql($sql);
            if ($selectSql === null) {
                throw new UnsupportedSqlException($sql, 'Cannot extract INSERT ... SELECT subquery');
            }

            $sourceColumns = $insertColumns !== [] ? $insertColumns : $tableColumns;
            $generatedIdentityStarts = $this->identityAllocator->allocateSelectStarts(
                $identityTable,
                $identityStrategies,
                $sourceColumns,
                $existingRows,
            );
            $projectedSql = $this->insertSelectRenderer->render(
                $this->withPrefix->carryPrefix($sql, $selectSql),
                $tableColumns,
                $sourceColumns,
                $columnDefaults,
                $generatedIdentityStarts,
            );
            $projectedSql = $this->projectUpsert($sql, $projectedSql, $tableName, $tableColumns, $tables);

            return $this->selectTransformer->transform($projectedSql, $tables);
        }

        $valueRows = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create())->topLevelClause(['DEFAULT', 'VALUES']) !== null
            ? [[]]
            : $this->parser->extractInsertValues($sql);
        if ($valueRows === []) {
            throw new UnsupportedSqlException($sql, 'Cannot extract INSERT values');
        }

        $selectParts = [];
        $columnTypes = $tables[$tableName]['columnTypes'] ?? [];
        foreach ($valueRows as $values) {
            $sourceColumns = $insertColumns !== [] || $values === [] ? $insertColumns : $tableColumns;
            try {
                $providedExpressions = $this->rowRenderer->providedExpressions($sourceColumns, $values);
            } catch (InvalidDefinitionException $exception) {
                throw new UnsupportedSqlException($sql, 'Insert values count does not match column count', $exception);
            }
            $generatedValues = $this->identityAllocator->allocateMissing(
                $identityTable,
                $identityStrategies,
                array_keys($providedExpressions),
                $existingRows,
            );
            $projected = $this->rowRenderer->render($tableColumns, $providedExpressions, $columnDefaults, $generatedValues);

            $selects = [];
            foreach ($projected as $column => $expr) {
                $type = $columnTypes[$column] ?? null;
                if ($type instanceof ColumnDeclaration) {
                    $expr = $this->castInsertExpression($expr, $type);
                }
                $selects[] = $expr . ' AS "' . $column . '"';
            }
            $selectParts[] = 'SELECT ' . implode(', ', $selects);
        }

        $selectSql = implode(' UNION ALL ', $selectParts);
        $selectSql = $this->projectUpsert($sql, $selectSql, $tableName, $tableColumns, $tables);

        return $this->selectTransformer->transform(
            $this->withPrefix->carryPrefix($sql, $selectSql),
            $tables,
        );
    }

    /**
     * Rewrites an ON CONFLICT so that the result says what it did to each row.
     *
     * @param string $sql Statement being read, as written
     * @param string $selectSql The SELECT the rows are read from
     * @param string $tableName Table it belongs to
     * @param list<string> $tableColumns Columns the table has
     * @param ShadowTables $tables Table name => what the shadow holds for it
     *
     * @return string What it answers
     */
    public function projectUpsert(
        string $sql,
        string $selectSql,
        string $tableName,
        array $tableColumns,
        array $tables,
    ): string {
        $conflict = $this->parser->extractOnConflictUpdateColumns($sql);
        $candidateKeys = new CandidateKeySet(
            isset($tables[$tableName]['candidateKeys']) ? $tables[$tableName]['candidateKeys'] : [],
        );
        $conflictPredicate = null;
        $target = $this->parser->extractOnConflictTarget($sql);
        if ($target !== null) {
            $resolved = $target->resolve(
                $candidateKeys,
                isset($tables[$tableName]['partialUniqueIndexes'])
                    ? $tables[$tableName]['partialUniqueIndexes']
                    : [],
                $sql,
            );
            $candidateKeys = $resolved['keys'];
            $conflictPredicate = $resolved['predicate'];
        }

        return $this->upsertProjector->project(
            $selectSql,
            $tableName,
            $tableColumns,
            $candidateKeys->keys(),
            $conflict['values'],
            $this->parser->extractOnConflictUpdateWhere($sql),
            $conflictPredicate,
        );
    }

    /**
     * Commit rewrite state.
     *
     */
    public function commitRewriteState(): void
    {
        $this->identityAllocator->commitProjection();
    }

    /**
     * Answers values under no keys of their own, in the order they were given.
     *
     * @param array<array-key, T> $values The values
     *
     * @return list<T> What it answers
     *
     * @template T
     */
    public static function orderedValues(array $values): array
    {
        $ordered = [];
        foreach ($values as $value) {
            $ordered[] = $value;
        }

        return $ordered;
    }

    /**
     * Writes an inserted value so that the database reads it as the column's type.
     *
     * @param string $expression Expression to read, as written
     * @param ColumnDeclaration $type How the column was declared
     *
     * @return string What it answers
     */
    public function castInsertExpression(string $expression, ColumnDeclaration $type): string
    {
        if ($type->family === ColumnTypeFamily::BOOLEAN && $expression === '?') {
            return "CAST(COALESCE(NULLIF(CAST(? AS TEXT), ''), 'false') AS BOOLEAN)";
        }

        return $this->castRenderer->renderCast($expression, $type);
    }
}
