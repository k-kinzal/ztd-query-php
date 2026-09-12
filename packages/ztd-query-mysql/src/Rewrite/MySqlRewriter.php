<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Statement;
use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\RewriteStateCommitter;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Sql\TransactionStatement;

/**
 * MySQL rewrite implementation for ZTD.
 *
 * Orchestrates parsing, classification, transformation, and mutation resolution.
 */
final class MySqlRewriter implements SqlRewriter, RewriteStateCommitter
{
    /**
     * Transaction Statement for the supplied MySQL input.
     */
    public function transactionStatement(string $sql): ?TransactionStatement
    {
        return (new MySqlTransactionStatementParser())->parse($sql);
    }

    private MySqlQueryGuard $guard;
    private ShadowStore $shadowStore;
    private TableDefinitionRegistry $registry;
    private MySqlTransformer $transformer;
    private MySqlMutationResolver $mutationResolver;
    private MySqlParser $parser;
    private MySqlCteShadowComposer $cteComposer;
    private ViewDefinitionSet $views;

    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(
        MySqlQueryGuard $guard,
        ShadowStore $shadowStore,
        TableDefinitionRegistry $registry,
        MySqlTransformer $transformer,
        MySqlMutationResolver $mutationResolver,
        MySqlParser $parser,
        ?ViewDefinitionSet $views = null,
    ) {
        $this->guard = $guard;
        $this->shadowStore = $shadowStore;
        $this->registry = $registry;
        $this->transformer = $transformer;
        $this->mutationResolver = $mutationResolver;
        $this->parser = $parser;
        $this->cteComposer = new MySqlCteShadowComposer();
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
        $logicalStatements = $this->parser->splitStatements($sql);
        if ($logicalStatements === []) {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }
        if (count($logicalStatements) !== 1) {
            throw new UnsupportedSqlException($sql, 'Multi-statement');
        }
        if (MySqlReadOnlyDiagnosticStatement::isSafe($sql)) {
            return new RewritePlan($sql, QueryKind::READ);
        }

        $statement = $this->parser->parseSingleLogicalStatement($sql);
        if ($statement === null) {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }

        if ($statement instanceof LoadStatement) {
            return $this->rewrite((new MySqlLoadDataProjector($this->registry))->project($sql, $statement));
        }

        return (new Rewrite\StatementRewriter($this->cteComposer, $this->guard, $this->mutationResolver, $this->parser, $this->registry, $this->shadowStore, $this->transformer, $this->views))->rewriteStatement($statement, $sql);
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
            $plans[] = $this->rewrite($statement);
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
     * Commit Rewrite State for the supplied MySQL input.
     */
    public function commitRewriteState(): void
    {
        $this->transformer->commitRewriteState();
    }

    /**
     * Empty Result Select for the supplied MySQL input.
     */
    public function emptyResultSelect(): string
    {
        return 'SELECT 1 WHERE FALSE';
    }
}
