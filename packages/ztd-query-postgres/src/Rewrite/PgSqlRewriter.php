<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Rewrite;

use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Postgres\Parse\PgSqlParser;
use ZtdQuery\Platform\Postgres\Parse\PgSqlReturningProjectionParser;
use ZtdQuery\Platform\Postgres\Parse\PgSqlTransactionStatementParser;
use ZtdQuery\Platform\Postgres\Parse\PgSqlWithPrefix;
use ZtdQuery\Rewrite\AffectedRowsMode;
use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\RewriteStateCommitter;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Sql\TransactionStatement;

/**
 * PostgreSQL rewrite implementation for ZTD.
 *
 * Orchestrates parsing, classification, transformation, and mutation resolution.
 * Uses Result Select Query approach (not RETURNING) for consistency across platforms.
 *
 * @phpstan-import-type ShadowTables from SqlTransformer
 */
final class PgSqlRewriter implements SqlRewriter, RewriteStateCommitter
{
    /**
     * Transaction statement.
     *
     * @param string $sql
     * @return ?TransactionStatement
     */
    public function transactionStatement(string $sql): ?TransactionStatement
    {
        return (new PgSqlTransactionStatementParser())->parse($sql);
    }

    private PgSqlQueryGuard $guard;
    private ShadowStore $shadowStore;
    private TableDefinitionRegistry $registry;
    private PgSqlTransformer $transformer;
    private PgSqlMutationResolver $mutationResolver;
    private PgSqlParser $parser;
    private PgSqlReturningProjectionParser $returningProjectionParser;

    /** @readonly */
    private PgSqlWithPrefix $withPrefix;
    private PgSqlPartitionPredicateRenderer $partitionPredicateRenderer;
    private ViewDefinitionSet $views;
    private PgSqlShadowTables $shadowTables;

    /**
     * Binds the instance to what it will work from.
     *
     * @param PgSqlQueryGuard $guard
     * @param ShadowStore $shadowStore
     * @param TableDefinitionRegistry $registry
     * @param PgSqlTransformer $transformer
     * @param PgSqlMutationResolver $mutationResolver
     * @param PgSqlParser $parser
     * @param ?ViewDefinitionSet $views
     */
    public function __construct(
        PgSqlQueryGuard $guard,
        ShadowStore $shadowStore,
        TableDefinitionRegistry $registry,
        PgSqlTransformer $transformer,
        PgSqlMutationResolver $mutationResolver,
        PgSqlParser $parser,
        ?ViewDefinitionSet $views = null,
    ) {
        $this->guard = $guard;
        $this->shadowStore = $shadowStore;
        $this->registry = $registry;
        $this->transformer = $transformer;
        $this->mutationResolver = $mutationResolver;
        $this->parser = $parser;
        $this->returningProjectionParser = new PgSqlReturningProjectionParser();
        $this->withPrefix = new PgSqlWithPrefix();
        $this->partitionPredicateRenderer = new PgSqlPartitionPredicateRenderer();
        $this->shadowTables = new PgSqlShadowTables($shadowStore, $registry, $this->partitionPredicateRenderer);
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
        $sql = trim($sql);
        if ($sql === '') {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }

        $statements = $this->splitStatements($sql);
        if ($statements === []) {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }

        if (count($statements) > 1) {
            throw new UnsupportedSqlException($sql, 'Multi-statement');
        }

        return $this->rewriteStatement($statements[0]);
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedSqlException When SQL is empty or unparseable.
     * @throws UnknownSchemaException When SQL references unknown tables/columns.
     */
    public function rewriteMultiple(string $sql): MultiRewritePlan
    {
        $sql = trim($sql);
        if ($sql === '') {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }

        $statements = $this->splitStatements($sql);
        if ($statements === []) {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }

        $plans = [];
        foreach ($statements as $stmt) {
            $plans[] = $this->rewriteStatement($stmt);
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
     * A read is rewritten to read the shadow's rows; a write is rewritten to
     * answer the rows it would have written, and paired with what applying it
     * would do. Nothing here writes to the database.
     *
     * @param string $sql Statement being read, as written
     *
     * @return RewritePlan What it answers
     *
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function rewriteStatement(string $sql): RewritePlan
    {
        if (PgSqlReadOnlyDiagnosticStatement::isSafe($sql)) {
            return new RewritePlan($sql, QueryKind::READ);
        }
        $kind = $this->guard->classify($sql);
        if ($kind === null) {
            throw new UnsupportedSqlException($sql, 'Statement type not supported');
        }
        if ($kind === QueryKind::SKIPPED) {
            return new RewritePlan($sql, QueryKind::SKIPPED);
        }

        $statementType = $this->parser->classifyStatement($sql) ?? '';
        if ($statementType === 'DO') {
            return new RewritePlan($sql, QueryKind::READ);
        }
        if ($kind === QueryKind::READ) {
            return $this->rewriteRead($sql);
        }
        if ($kind === QueryKind::DDL_SIMULATED) {
            return $this->rewriteDefinition($sql, $statementType);
        }

        return $this->rewriteWrite($sql, $statementType, $kind);
    }

    /**
     * Rewrites a statement that only reads, to read the shadow instead.
     *
     * @param string $sql Statement being read, as written
     *
     * @return RewritePlan The statement to run against the shadow
     *
     * @throws UnknownSchemaException When it reads a table nothing has declared
     */
    public function rewriteRead(string $sql): RewritePlan
    {
        $unknown = $this->firstUnknownTable($sql);
        if ($unknown !== null) {
            throw new UnknownSchemaException($sql, $unknown, 'table');
        }

        return new RewritePlan($this->transformer->transform($sql, $this->buildTableContext()), QueryKind::READ);
    }

    /**
     * Answers the first table a statement reads that the shadow does not know.
     *
     * A shadow that has been told nothing knows no table, so nothing is
     * refused for being missing; a name the statement declares for itself is
     * not a table, so it is not one the shadow is missing either.
     *
     * @param string $sql Statement being read, as written
     *
     * @return string|null The table, or null where the shadow knows every one of them
     */
    public function firstUnknownTable(string $sql): ?string
    {
        if (!$this->hasSchemaContext()) {
            return null;
        }

        $declaredCtes = array_fill_keys($this->withPrefix->declaredCteNames($sql), true);
        foreach ($this->parser->extractSelectTableNames($sql) as $tableName) {
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
     * Rewrites a statement that changes what a table is.
     *
     * There are no rows to answer for a definition, so the statement to run
     * reads nothing back -- except where the definition carries a SELECT,
     * whose rows are what the new table is to hold.
     *
     * @param string $sql Statement being read, as written
     * @param string $statementType What the parser takes the statement to be
     *
     * @return RewritePlan The statement to run, and what applying it would do
     *
     * @throws UnsupportedSqlException When ZTD cannot simulate what it asks for
     * @throws UnknownSchemaException When it reads a table nothing has declared
     */
    public function rewriteDefinition(string $sql, string $statementType): RewritePlan
    {
        $mutation = $this->mutationResolver->resolve($sql, $statementType, QueryKind::DDL_SIMULATED);
        $selectSql = $statementType === 'CREATE_TABLE' && $this->parser->hasCreateTableAsSelect($sql)
            ? $this->parser->extractCreateTableSelectSql($sql)
            : null;
        if ($selectSql !== null) {
            $transformed = $this->transformer->transform($selectSql, $this->buildTableContext());

            return new RewritePlan($transformed, QueryKind::DDL_SIMULATED, $mutation);
        }

        return new RewritePlan($this->emptyResultSelect(), QueryKind::DDL_SIMULATED, $mutation);
    }

    /**
     * Rewrites a statement that writes, to answer the rows it would have written.
     *
     * @param string $sql Statement being read, as written
     * @param string $statementType What the parser takes the statement to be
     * @param QueryKind $kind What the statement was taken to be
     *
     * @return RewritePlan The statement to run, and what applying it would do
     *
     * @throws UnsupportedSqlException When ZTD cannot simulate what it asks for
     * @throws UnknownSchemaException When it writes a table nothing has declared
     */
    public function rewriteWrite(string $sql, string $statementType, QueryKind $kind): RewritePlan
    {
        $mutation = $this->mutationResolver->resolve($sql, $statementType, $kind);
        if ($statementType === 'TRUNCATE') {
            return new RewritePlan($this->emptyResultSelect(), QueryKind::WRITE_SIMULATED, $mutation);
        }

        return new RewritePlan(
            $this->transformer->transform($sql, $this->buildTableContext()),
            QueryKind::WRITE_SIMULATED,
            $mutation,
            $this->returningProjectionParser->parse($sql),
            $statementType === 'MERGE' ? AffectedRowsMode::Changed : AffectedRowsMode::Matched,
        );
    }

    /**
     * Answers everything the shadow holds, in the form a transformer is handed.
     *
     * @return ShadowTables Table name => what the shadow holds for it
     */
    public function buildTableContext(): array
    {
        return $this->shadowTables->of($this->views);
    }

    /**
     * Answers which table a partition's rows are actually held in.
     *
     * @param string $tableName Table it belongs to
     *
     * @return string What it answers
     */
    public function storageTable(string $tableName): string
    {
        return $this->shadowTables->storageTable($tableName);
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
        return 'SELECT 1 WHERE FALSE';
    }
}
