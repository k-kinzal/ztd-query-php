<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite;

use PhpMyAdmin\SqlParser\Statement;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use PhpMyAdmin\SqlParser\Statements\WithStatement;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Dialect\MySqlStatementOptions;
use ZtdQuery\Platform\MySql\Parse\MySqlParser;
use ZtdQuery\Platform\MySql\Parse\MySqlTransactionStatementParser;
use ZtdQuery\Platform\MySql\Rewrite\LoadData\MySqlLoadDataProjector;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
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
 * MySQL rewrite implementation for ZTD.
 *
 * Orchestrates parsing, classification, transformation, and mutation resolution.
 *
 * @phpstan-import-type ShadowTables from SqlTransformer
 */
final class MySqlRewriter implements SqlRewriter, RewriteStateCommitter
{
    /**
     * Transaction statement.
     *
     * @param string $sql
     * @return ?TransactionStatement
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
    private MySqlShadowTables $shadowTables;
    private MySqlAlterSupport $alters;
    private MySqlKnownTables $known;

    /**
     * Binds the instance to what it will work from.
     *
     * @param MySqlQueryGuard $guard
     * @param ShadowStore $shadowStore
     * @param TableDefinitionRegistry $registry
     * @param MySqlTransformer $transformer
     * @param MySqlMutationResolver $mutationResolver
     * @param MySqlParser $parser
     * @param ?ViewDefinitionSet $views
     */
    public function __construct(
        MySqlQueryGuard $guard,
        ShadowStore $shadowStore,
        TableDefinitionRegistry $registry,
        MySqlTransformer $transformer,
        MySqlMutationResolver $mutationResolver,
        MySqlParser $parser,
        ?ViewDefinitionSet $views = null,
        private readonly MySqlStatementOptions $options = new MySqlStatementOptions(),
    ) {
        $this->guard = $guard;
        $this->shadowStore = $shadowStore;
        $this->registry = $registry;
        $this->transformer = $transformer;
        $this->mutationResolver = $mutationResolver;
        $this->parser = $parser;
        $this->cteComposer = new MySqlCteShadowComposer();
        $this->views = $views ?? new ViewDefinitionSet();
        $this->shadowTables = new MySqlShadowTables($shadowStore, $registry);
        $this->alters = new MySqlAlterSupport($this->options);
        $this->known = new MySqlKnownTables($shadowStore, $registry, $this->views, ctes: $this->cteComposer);
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

        return $this->rewriteStatement($statement, $sql);
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
     * Commit rewrite state.
     *
     */
    public function commitRewriteState(): void
    {
        $this->transformer->commitRewriteState();
    }

    /**
     * Answers how one statement is to be run against the shadow.
     *
     * A read is rewritten to read the shadow's rows; a write is rewritten to
     * answer the rows it would have written, and paired with what applying it
     * would do. Nothing here writes to the database.
     *
     * @param Statement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return RewritePlan The statement to run, and what to do with what it answers
     *
     * @throws UnsupportedSqlException When ZTD cannot simulate the statement
     * @throws UnknownSchemaException When it reads or writes a table nothing has declared
     */
    public function rewriteStatement(Statement $statement, string $sql): RewritePlan
    {
        $kind = $statement instanceof WithStatement
            ? $this->guard->classify($sql)
            : $this->guard->classifyStatement($statement);
        if ($kind === null) {
            throw new UnsupportedSqlException($sql, 'Statement type not supported');
        }
        if ($statement instanceof LoadStatement) {
            return $this->rewrite((new MySqlLoadDataProjector($this->registry))->project($sql, $statement));
        }
        if ($kind === QueryKind::READ) {
            return $this->rewriteRead($sql);
        }
        if ($kind === QueryKind::DDL_SIMULATED) {
            return $this->rewriteDefinition($statement, $sql);
        }

        return $this->rewriteWrite($statement, $sql, $kind);
    }

    /**
     * Rewrites a statement that only reads, to read the shadow instead.
     *
     * @param string $sql The statement, as written
     *
     * @return RewritePlan The statement to run against the shadow
     *
     * @throws UnknownSchemaException When it reads a table nothing has declared
     */
    public function rewriteRead(string $sql): RewritePlan
    {
        if ($this->hasSchemaContext()) {
            $unknownTable = $this->findUnknownTable($sql);
            if ($unknownTable !== null) {
                throw new UnknownSchemaException($sql, $unknownTable, 'table');
            }
        }

        return new RewritePlan($this->transformer->transform($sql, $this->buildTableContext()), QueryKind::READ);
    }

    /**
     * Rewrites a statement that changes what a table is.
     *
     * There are no rows to answer for a definition, so the statement to run
     * reads nothing back -- except where the definition carries a SELECT,
     * whose rows are what the new table is to hold.
     *
     * @param Statement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return RewritePlan The statement to run, and what applying it would do
     *
     * @throws UnsupportedSqlException When ZTD cannot simulate what it asks for
     * @throws UnknownSchemaException When it reads a table nothing has declared
     */
    public function rewriteDefinition(Statement $statement, string $sql): RewritePlan
    {
        if ($statement instanceof AlterStatement && $this->hasUnsupportedAlterOperation($statement, $sql)) {
            throw new UnsupportedSqlException($sql, 'Unsupported ALTER TABLE operation');
        }

        $mutation = $this->mutationResolver->resolve($sql, $statement, QueryKind::DDL_SIMULATED);
        if ($statement instanceof CreateStatement && $statement->select !== null) {
            $selectSql = $this->transformer->transform($statement->select->build(), $this->buildTableContext());

            return new RewritePlan($selectSql, QueryKind::DDL_SIMULATED, $mutation);
        }

        return new RewritePlan($this->emptyResultSelect(), QueryKind::DDL_SIMULATED, $mutation);
    }

    /**
     * Rewrites a statement that writes, to answer the rows it would have written.
     *
     * A statement written with a CTE prefix is resolved against the statement
     * the prefix leads to, because that is the one doing the writing.
     *
     * @param Statement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     * @param QueryKind $kind What the statement was taken to be
     *
     * @return RewritePlan The statement to run, and what applying it would do
     *
     * @throws UnsupportedSqlException When ZTD cannot simulate what it asks for
     * @throws UnknownSchemaException When it writes a table nothing has declared
     */
    public function rewriteWrite(Statement $statement, string $sql, QueryKind $kind): RewritePlan
    {
        $mutationStatement = $statement;
        if ($statement instanceof WithStatement) {
            $mainStatements = $this->parser->parse($this->cteComposer->statementSql($sql));
            $mutationStatement = $mainStatements[0] ?? $statement;
        }

        $mutation = $this->mutationResolver->resolve($sql, $mutationStatement, $kind);
        if ($statement instanceof TruncateStatement) {
            return new RewritePlan($this->emptyResultSelect(), QueryKind::WRITE_SIMULATED, $mutation);
        }
        if ($statement instanceof ReplaceStatement) {
            $this->ensureReplaceColumns($statement, $sql);
        }

        $transformedSql = $this->transformer->transform($sql, $this->buildTableContext());

        return new RewritePlan($transformedSql, QueryKind::WRITE_SIMULATED, $mutation);
    }

    /**
     * Answers everything the shadow holds, in the form a transformer is handed.
     *
     * A view is carried as the statement that defines it rather than as rows,
     * because a view has none of its own -- and one whose name a table has
     * taken is left out, because the table is what that name now means.
     *
     * @return ShadowTables Table name => what the shadow holds for it
     */
    public function buildTableContext(): array
    {
        return $this->shadowTables->of($this->views);
    }

    /**
     * Refuses a REPLACE whose columns nothing can say.
     *
     * A REPLACE that names no columns writes to all of them, so ZTD has to
     * know what they are -- from the rows the shadow holds, or from what
     * declared the table. With neither, there is nothing to write.
     *
     * @param ReplaceStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @throws UnsupportedSqlException When nothing can say what the table's columns are
     */
    public function ensureReplaceColumns(ReplaceStatement $statement, string $sql): void
    {
        $tableName = self::resolveIntoTableName($statement->into);
        if ($tableName === null) {
            return;
        }

        $columns = $statement->into->columns ?? [];
        $columns = array_values(array_filter($columns, 'is_string'));
        if ($columns !== []) {
            return;
        }

        $rows = $this->shadowStore->get($tableName);
        if ($rows !== []) {
            return;
        }

        $definition = $this->registry->get($tableName);
        if ($definition !== null) {
            return;
        }

        throw new UnsupportedSqlException($sql, 'Cannot determine columns');
    }

    /**
     * Answers the first table a statement reads that the shadow does not know.
     *
     * @param string $sql The statement, as written
     *
     * @return string|null The table, or null where the shadow knows every one of them
     */
    public function findUnknownTable(string $sql): ?string
    {
        return $this->known->firstUnknownIn($sql);
    }

    /**
     * Reports whether the shadow knows a table at all.
     *
     * @param string $tableName Name to look for
     *
     * @return bool True when it has rows for it, a declaration of it, or a view by that name
     */
    public function tableExists(string $tableName): bool
    {
        return $this->known->knows($tableName);
    }

    /**
     * Reports whether the shadow has been told anything at all.
     *
     * A shadow that has been told nothing cannot say that a table is unknown,
     * because every table is -- so nothing is refused for being missing.
     *
     * @return bool True when anything has been declared or filled in
     */
    public function hasSchemaContext(): bool
    {
        return $this->known->hasAnything();
    }

    /**
     * Reports whether an ALTER asks for something ZTD does not model.
     *
     * Indexes, column defaults and row order are all things the shadow does
     * not hold, so a statement that changes one of them would appear to have
     * been simulated while having done nothing.
     *
     * @param AlterStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return bool True when ZTD cannot simulate what it asks for
     */
    public function hasUnsupportedAlterOperation(AlterStatement $statement, string $sql): bool
    {
        if ($this->alters->refusesStatement($sql)) {
            return true;
        }
        foreach ($statement->altered ?? [] as $operation) {
            if ($this->alters->refusesOperation($operation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the table an INTO clause names.
     *
     * @param \PhpMyAdmin\SqlParser\Components\IntoKeyword|null $into The clause, or null where the statement wrote none
     *
     * @return string|null The table, or null where the clause names none
     */
    public static function resolveIntoTableName(?\PhpMyAdmin\SqlParser\Components\IntoKeyword $into): ?string
    {
        if ($into === null || $into->dest === null) {
            return null;
        }
        $dest = $into->dest;
        return is_string($dest) ? $dest : ($dest->table ?? null);
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
