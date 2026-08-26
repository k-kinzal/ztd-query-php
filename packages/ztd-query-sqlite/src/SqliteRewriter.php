<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Sqlite\Mutation\AlterTableMutation;
use ZtdQuery\Platform\Sqlite\Transformer\SqliteTransformer;
use ZtdQuery\Platform\ValueRenderer;
use ZtdQuery\Rewrite\AffectedRowsMode;
use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\RewriteStateCommitter;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\IdentityGenerationStrategy;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Sql\TransactionStatement;

/**
 * SQLite rewrite implementation for ZTD.
 *
 * Orchestrates parsing, classification, transformation, and mutation resolution.
 * Uses Result Select Query approach (not RETURNING) for consistency.
 *
 * @phpstan-import-type ShadowTables from SqlTransformer
 * @phpstan-import-type RenderableValue from ValueRenderer
 * @phpstan-import-type ShadowRows from SqlTransformer
 */
final class SqliteRewriter implements SqlRewriter, RewriteStateCommitter
{
    /**
     * Transaction statement.
     *
     * @param string $sql
     * @return ?TransactionStatement
     */
    public function transactionStatement(string $sql): ?TransactionStatement
    {
        return (new SqliteTransactionStatementParser())->parse($sql);
    }

    private SqliteQueryGuard $guard;
    private ShadowStore $shadowStore;
    private TableDefinitionRegistry $registry;
    private SqliteTransformer $transformer;
    private SqliteMutationResolver $mutationResolver;
    private SqliteParser $parser;
    private SqliteReturningProjectionParser $returningProjectionParser;
    private SqliteCteShadowComposer $cteComposer;
    private ViewDefinitionSet $views;

    /**
     * Binds the instance to what it will work from.
     *
     * @param SqliteQueryGuard $guard
     * @param ShadowStore $shadowStore
     * @param TableDefinitionRegistry $registry
     * @param SqliteTransformer $transformer
     * @param SqliteMutationResolver $mutationResolver
     * @param SqliteParser $parser
     * @param ?ViewDefinitionSet $views
     */
    public function __construct(
        SqliteQueryGuard $guard,
        ShadowStore $shadowStore,
        TableDefinitionRegistry $registry,
        SqliteTransformer $transformer,
        SqliteMutationResolver $mutationResolver,
        SqliteParser $parser,
        ?ViewDefinitionSet $views = null,
    ) {
        $this->guard = $guard;
        $this->shadowStore = $shadowStore;
        $this->registry = $registry;
        $this->transformer = $transformer;
        $this->mutationResolver = $mutationResolver;
        $this->parser = $parser;
        $this->returningProjectionParser = new SqliteReturningProjectionParser();
        $this->cteComposer = new SqliteCteShadowComposer();
        $this->views = $views ?? new ViewDefinitionSet();
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedSqlException When SQL is empty, unparseable, or multi-statement.
     * @throws UnknownSchemaException When SQL references unknown tables/columns.
     */
    public function rewrite(string $sql): RewritePlan
    {
        $statements = $this->parser->splitStatements($sql);
        if ($statements === []) {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }

        if (count($statements) === 1) {
            return $this->rewriteStatement($statements[0], $sql);
        }

        throw new UnsupportedSqlException($sql, 'Multi-statement');
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedSqlException When SQL is empty or unparseable.
     * @throws UnknownSchemaException When SQL references unknown tables/columns.
     */
    public function rewriteMultiple(string $sql): MultiRewritePlan
    {
        $statements = $this->splitStatements($sql);

        if ($statements === []) {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }

        $plans = [];
        foreach ($statements as $statement) {
            $plans[] = $this->rewriteStatement($statement, $statement);
        }

        return new MultiRewritePlan($plans);
    }

    /**
     * {@inheritDoc}
     */
    public function splitStatements(string $sql): array
    {
        return $this->parser->splitStatements($sql);
    }

    /**
     * Commit rewrite state.
     *
     */
    public function commitRewriteState(): void
    {
        $this->transformer->commitRewriteState();
    }

    /**
     * @throws UnknownSchemaException
     */
    /**
     * Answers how one statement is to be run against the shadow.
     *
     * @param string $stmtSql The stmt sql
     * @param string $originalSql Statement being rewritten, as written
     *
     * @return RewritePlan What it answers
     *
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function rewriteStatement(string $stmtSql, string $originalSql): RewritePlan
    {
        if (SqliteInMemoryAttachStatement::isSafe($stmtSql)) {
            return new RewritePlan($stmtSql, QueryKind::READ);
        }
        if (SqliteReadOnlyDiagnosticStatement::isSafe($stmtSql)) {
            return new RewritePlan($stmtSql, QueryKind::READ);
        }
        $kind = $this->guard->classify($stmtSql);
        if ($kind === null) {
            throw new UnsupportedSqlException($originalSql, 'Statement type not supported');
        }

        $tableContext = $this->buildTableContext();

        if ($kind === QueryKind::READ) {
            if ($this->hasSchemaContext()) {
                $unknownTable = $this->findUnknownTable($stmtSql);
                if ($unknownTable !== null) {
                    throw new UnknownSchemaException($originalSql, $unknownTable, 'table');
                }
            }

            $transformedSql = $this->transformer->transform($stmtSql, $tableContext);

            return new RewritePlan($transformedSql, QueryKind::READ);
        }

        if ($kind === QueryKind::DDL_SIMULATED) {
            $mutation = $this->mutationResolver->resolve($stmtSql, $kind);
            if ($mutation instanceof AlterTableMutation) {
                return new RewritePlan(
                    $this->transformer->transform($mutation->resultSelect(), $tableContext),
                    QueryKind::DDL_SIMULATED,
                    $mutation,
                    affectedRowsMode: AffectedRowsMode::None,
                );
            }

            return new RewritePlan($this->emptyResultSelect(), QueryKind::DDL_SIMULATED, $mutation);
        }

        $mutation = $this->mutationResolver->resolve($stmtSql, $kind);

        $transformedSql = $this->transformer->transform($stmtSql, $tableContext);

        return new RewritePlan(
            $transformedSql,
            QueryKind::WRITE_SIMULATED,
            $mutation,
            $this->returningProjectionParser->parse($stmtSql),
            AffectedRowsMode::Matched,
        );
    }

    /**
     * Answers everything the shadow holds, in the form a transformer is handed.
     *
     * @return ShadowTables Table name => what the shadow holds for it
     */
    public function buildTableContext(): array
    {
        $context = [];
        $allData = $this->shadowStore->getAll();

        foreach ($allData as $tableName => $rows) {
            $definition = $this->registry->get($tableName);
            if ($definition !== null) {
                $context[$tableName] = self::contextFromDefinition($definition, $rows);
            } else {
                $columns = [];
                if ($rows !== []) {
                    $columns = array_keys($rows[0]);
                    foreach ($rows as $row) {
                        foreach (array_keys($row) as $column) {
                            if (!in_array($column, $columns, true)) {
                                $columns[] = $column;
                            }
                        }
                    }
                }

                $context[$tableName] = [
                    'rows' => $rows,
                    'columns' => $columns,
                    'columnTypes' => [],
                    'columnDefaults' => [],
                    'identityStrategies' => [],
                    'generatedExpressions' => [],
                    'primaryKeys' => [],
                    'candidateKeys' => [],
                ];
            }
        }

        $allDefinitions = $this->registry->getAll();
        foreach ($allDefinitions as $tableName => $definition) {
            if (isset($context[$tableName])) {
                continue;
            }

            $context[$tableName] = self::contextFromDefinition($definition, []);
        }
        foreach ($this->registry->getAllRemoved() as $tableName => $definition) {
            $context[$tableName] = self::contextFromDefinition($definition, []);
        }

        foreach ((new SqliteViewShadowRenderer())->render($this->views, array_keys($context)) as $viewName => $viewSql) {
            if (isset($context[$viewName])) {
                continue;
            }
            $context[$viewName] = ['viewSql' => $viewSql];
        }

        return $context;
    }

    /**
     * Answers what a transformer is handed for a table nothing has filled in.
     *
     * @param TableDefinition $definition What the table holds
     * @param list<array<string, RenderableValue>> $rows Rows the shadow holds for it
     *
     * @return array{rows: list<array<string, RenderableValue>>, columns: array<int, string>, columnTypes: array<string, ColumnDeclaration>, primaryKeys: array<int, string>, candidateKeys: array<string, array<int, string>>, columnDefaults: array<string, string>, identityStrategies: array<string, IdentityGenerationStrategy>, generatedExpressions: array<string, string>} What the shadow holds for that table
     */
    public static function contextFromDefinition(TableDefinition $definition, array $rows): array
    {
        return [
            'rows' => $rows,
            'columns' => $definition->columns,
            'columnTypes' => $definition->typedColumns,
            'columnDefaults' => $definition->columnDefaults,
            'identityStrategies' => $definition->identityStrategies,
            'generatedExpressions' => $definition->generatedExpressions,
            'primaryKeys' => $definition->primaryKeys,
            'candidateKeys' => $definition->candidateKeys()->keys(),
        ];
    }

    /**
     * Answers the first table a statement reads that the shadow does not know.
     *
     * @param string $sql Statement being read, as written
     *
     * @return string|null What it answers
     */
    public function findUnknownTable(string $sql): ?string
    {
        $type = $this->parser->classifyStatement($sql);
        if ($type !== 'SELECT') {
            return null;
        }

        $tableNames = $this->parser->extractSelectTables($sql);
        $declaredCtes = array_fill_keys($this->cteComposer->declaredCteNames($sql), true);

        foreach ($tableNames as $tableName) {
            if (isset($declaredCtes[strtolower($tableName)])) {
                continue;
            }
            if (!$this->tableExists($tableName)) {
                return $tableName;
            }
        }

        return null;
    }

    /**
     * Reports whether the shadow knows a table at all.
     *
     * @param string $tableName Table it belongs to
     *
     * @return bool What it answers
     */
    public function tableExists(string $tableName): bool
    {
        if ($this->shadowStore->has($tableName)) {
            return true;
        }

        if ($this->registry->has($tableName)) {
            return true;
        }

        if ($this->registry->isRemoved($tableName)) {
            return true;
        }

        if ($this->views->has($tableName)) {
            return true;
        }

        return false;
    }

    /**
     * Reports whether the shadow has been told anything at all.
     *
     * @return bool What it answers
     */
    public function hasSchemaContext(): bool
    {
        if ($this->shadowStore->getAll() !== []) {
            return true;
        }

        if ($this->registry->hasAnyTables()) {
            return true;
        }

        if ($this->views->hasAnyViews()) {
            return true;
        }

        return false;
    }

    /**
     * Empty result select.
     *
     * @return string
     */
    public function emptyResultSelect(): string
    {
        return 'SELECT 1 WHERE 0';
    }
}
