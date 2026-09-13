<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\RewriteStateCommitter;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Sql\TransactionStatement;

/**
 * PostgreSQL rewrite implementation for ZTD.
 *
 * Orchestrates parsing, classification, transformation, and mutation resolution.
 * Uses Result Select Query approach (not RETURNING) for consistency across platforms.
 */
final class PgSqlRewriter implements SqlRewriter, RewriteStateCommitter
{
    /**
     * Parses a transaction control statement into a shadow transaction operation.
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
    private PgSqlCteShadowComposer $cteComposer;
    private PgSqlPartitionPredicateRenderer $partitionPredicateRenderer;
    private ViewDefinitionSet $views;

    /**
     * Initializes the collaborators and state used by this rewriter.
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
        $this->cteComposer = new PgSqlCteShadowComposer();
        $this->partitionPredicateRenderer = new PgSqlPartitionPredicateRenderer();
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

        return (new Session\StatementRewriter($this->cteComposer, $this->guard, $this->mutationResolver, $this->parser, $this->partitionPredicateRenderer, $this->registry, $this->returningProjectionParser, $this->shadowStore, $this->transformer, $this->views))->rewriteStatement($statements[0]);
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
            $plans[] = (new Session\StatementRewriter($this->cteComposer, $this->guard, $this->mutationResolver, $this->parser, $this->partitionPredicateRenderer, $this->registry, $this->returningProjectionParser, $this->shadowStore, $this->transformer, $this->views))->rewriteStatement($stmt);
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
     * Commits staged generated identity values after a successful rewrite.
     */
    public function commitRewriteState(): void
    {
        $this->transformer->commitRewriteState();
    }

    /**
     * Returns a PostgreSQL SELECT that produces no rows.
     */
    public function emptyResultSelect(): string
    {
        return 'SELECT 1 WHERE FALSE';
    }
}
