<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Sqlite\Transformer\SqliteTransformer;
use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\RewriteStateCommitter;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Sql\TransactionStatement;

/**
 * SQLite rewrite implementation for ZTD.
 *
 * Orchestrates parsing, classification, transformation, and mutation resolution.
 * Uses Result Select Query approach (not RETURNING) for consistency.
 */
final class SqliteRewriter implements SqlRewriter, RewriteStateCommitter
{
    /**
     * Returns transaction statement.
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
     * Binds the dependencies used by this operation.
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
            return (new Rewriting\Statement\StatementRewriter($this->cteComposer, $this->guard, $this->mutationResolver, $this->parser, $this->registry, $this->returningProjectionParser, $this->shadowStore, $this->transformer, $this->views))->rewriteStatement($statements[0], $sql);
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
            $plans[] = (new Rewriting\Statement\StatementRewriter($this->cteComposer, $this->guard, $this->mutationResolver, $this->parser, $this->registry, $this->returningProjectionParser, $this->shadowStore, $this->transformer, $this->views))->rewriteStatement($statement, $statement);
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
     * Commits the transformer state after a rewrite completes.
     */
    public function commitRewriteState(): void
    {
        $this->transformer->commitRewriteState();
    }

    /**
     * Returns a SELECT that produces no result rows.
     */
    public function emptyResultSelect(): string
    {
        return 'SELECT 1 WHERE 0';
    }
}
