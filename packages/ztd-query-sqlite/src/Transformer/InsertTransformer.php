<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite\Transformer;

use RuntimeException;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\Sqlite\SqliteCastRenderer;
use ZtdQuery\Platform\Sqlite\SqliteCteShadowComposer;
use ZtdQuery\Platform\Sqlite\SqliteLexerProfile;
use ZtdQuery\Platform\Sqlite\SqliteNativeUpsertProjector;
use ZtdQuery\Platform\Sqlite\SqliteParser;
use ZtdQuery\Platform\ValueRenderer;
use ZtdQuery\Rewrite\ShadowIdentityAllocator;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\ColumnDeclaration;
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
 *
 * @phpstan-import-type RenderableValue from ValueRenderer
 */
final class InsertTransformer implements SqlTransformer
{
    private SqliteParser $parser;
    private SelectTransformer $selectTransformer;
    private CastRenderer $castRenderer;
    private InsertRowRenderer $rowRenderer;
    private ShadowIdentityAllocator $identityAllocator;
    private InsertSelectRenderer $insertSelectRenderer;
    private SqliteCteShadowComposer $cteComposer;
    private SqliteNativeUpsertProjector $upsertProjector;

    /**
     * Binds the instance to what it will work from.
     *
     * @param SqliteParser $parser
     * @param SelectTransformer $selectTransformer
     * @param ?CastRenderer $castRenderer
     */
    public function __construct(
        SqliteParser $parser,
        SelectTransformer $selectTransformer,
        ?CastRenderer $castRenderer = null,
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->castRenderer = $castRenderer ?? new SqliteCastRenderer();
        $this->rowRenderer = new InsertRowRenderer();
        $this->identityAllocator = new ShadowIdentityAllocator();
        $this->insertSelectRenderer = new InsertSelectRenderer();
        $this->cteComposer = new SqliteCteShadowComposer();
        $this->upsertProjector = new SqliteNativeUpsertProjector();
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedSqlException
     */
    public function transform(string $sql, array $tables): string
    {
        $this->identityAllocator->beginProjection();
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
        $selectSql = $this->upsertProjector->project(
            $selectSql,
            $tableName,
            $tableColumns,
            isset($tables[$tableName]['candidateKeys']) ? $tables[$tableName]['candidateKeys'] : [],
            $this->parser->extractOnConflictUpdates($sql),
            $this->parser->extractOnConflictUpdateWhere($sql),
        );

        return $this->selectTransformer->transform(
            $this->cteComposer->carryPrefix($sql, $selectSql),
            $tables,
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
     * Writes the SELECT that answers the rows an INSERT would write.
     *
     * @param string $sql Statement being read, as written
     * @param string $tableName Table it belongs to
     * @param list<string> $tableColumns The table columns
     * @param list<string> $insertColumns The insert columns
     * @param array<string, ColumnDeclaration> $columnTypes The column types
     * @param array<string, string> $columnDefaults The column defaults
     * @param array<string, \ZtdQuery\Schema\IdentityGenerationStrategy> $identityStrategies The identity strategies
     * @param list<array<string, RenderableValue>> $existingRows The existing rows
     *
     * @return string What it answers
     *
     * @throws RuntimeException
     */
    public function buildInsertSelect(
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
                throw new RuntimeException('Failed to extract SELECT from INSERT ... SELECT.');
            }

            $sourceColumns = $insertColumns !== [] ? $insertColumns : $tableColumns;
            $generatedIdentityStarts = $this->identityAllocator->allocateSelectStarts(
                $tableName,
                $identityStrategies,
                $sourceColumns,
                $existingRows,
            );

            return $this->insertSelectRenderer->render(
                $selectSql,
                $tableColumns,
                $sourceColumns,
                $columnDefaults,
                $generatedIdentityStarts,
            );
        }

        $valueSets = SqlTokenStream::tokenize($sql, SqliteLexerProfile::create())->topLevelClause(['DEFAULT', 'VALUES']) !== null
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

        throw new RuntimeException('Insert statement has no values to project.');
    }

    /**
     * Writes the SELECT that answers one row.
     *
     * @param array<int, string> $values The values
     * @param string $tableName Table it belongs to
     * @param list<string> $tableColumns The table columns
     * @param list<string> $insertColumns The insert columns
     * @param array<string, ColumnDeclaration> $columnTypes The column types
     * @param array<string, string> $columnDefaults The column defaults
     * @param array<string, \ZtdQuery\Schema\IdentityGenerationStrategy> $identityStrategies The identity strategies
     * @param list<array<string, RenderableValue>> $existingRows The existing rows
     *
     * @return string What it answers
     *
     * @throws RuntimeException
     */
    public function buildInsertRowSelect(
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
        try {
            $providedExpressions = $this->rowRenderer->providedExpressions($sourceColumns, $values);
        } catch (InvalidDefinitionException $exception) {
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
            if ($type instanceof ColumnDeclaration) {
                $expr = $this->castRenderer->renderCast($expr, $type);
            }
            $selects[] = $expr . ' AS "' . $column . '"';
        }

        return 'SELECT ' . implode(', ', $selects);
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
}
