<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite;

use PhpMyAdmin\SqlParser\Statement;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use PhpMyAdmin\SqlParser\Statements\WithStatement;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlCteShadowComposer;
use ZtdQuery\Platform\MySql\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Statement Rewriter.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class StatementRewriter
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private MySqlCteShadowComposer $cteComposer, private MySqlQueryGuard $guard, private MySqlMutationResolver $mutationResolver, private MySqlParser $parser, private TableDefinitionRegistry $registry, private ShadowStore $shadowStore, private MySqlTransformer $transformer, private ViewDefinitionSet $views)
    {
    }
    /**
     * Rewrite Statement for the supplied MySQL input.
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function rewriteStatement(Statement $statement, string $sql): RewritePlan
    {
        $kind = $statement instanceof WithStatement
            ? $this->guard->classify($sql)
            : $this->guard->classifyStatement($statement);
        if ($kind === null) {
            throw new UnsupportedSqlException($sql, 'Statement type not supported');
        }

        $tableContext = (new Context\TableContext($this->cteComposer, $this->registry, $this->shadowStore, $this->views))->buildTableContext();

        if ($kind === QueryKind::READ) {
            (new Context\TableContext($this->cteComposer, $this->registry, $this->shadowStore, $this->views))->requireKnownTables($sql);

            $transformedSql = $this->transformer->transform($sql, $tableContext);
            return new RewritePlan($transformedSql, QueryKind::READ);
        }

        if ($kind === QueryKind::DDL_SIMULATED) {
            if ($statement instanceof AlterStatement && (new Validation\AlterTableGuard())->hasUnsupportedAlterOperation($statement, $sql)) {
                throw new UnsupportedSqlException($sql, 'Unsupported ALTER TABLE operation');
            }

            $mutation = $this->mutationResolver->resolve($sql, $statement, $kind);

            if ($statement instanceof CreateStatement && $statement->select !== null) {
                $selectSql = $statement->select->build();
                $transformedSelectSql = $this->transformer->transform($selectSql, $tableContext);
                return new RewritePlan($transformedSelectSql, QueryKind::DDL_SIMULATED, $mutation);
            }

            return new RewritePlan('SELECT 1 WHERE FALSE', QueryKind::DDL_SIMULATED, $mutation);
        }

        $mutationStatement = $this->mutationStatement($statement, $sql);

        $mutation = $this->mutationResolver->resolve($sql, $mutationStatement, $kind);

        if ($statement instanceof TruncateStatement) {
            return new RewritePlan('SELECT 1 WHERE FALSE', QueryKind::WRITE_SIMULATED, $mutation);
        }

        if ($statement instanceof ReplaceStatement) {
            (new Validation\ReplaceColumns($this->registry, $this->shadowStore))->ensureReplaceColumns($statement, $sql);
        }

        $transformedSql = $this->transformer->transform($sql, $tableContext);
        return new RewritePlan($transformedSql, QueryKind::WRITE_SIMULATED, $mutation);
    }
    /**
     * Resolve the main mutation statement behind an optional CTE prefix.
     */
    public function mutationStatement(Statement $statement, string $sql): Statement
    {
        $mutationStatement = $statement;
        if ($statement instanceof WithStatement) {
            $mainStatements = $this->parser->parse($this->cteComposer->statementSql($sql));
            $mutationStatement = $mainStatements[0] ?? $statement;
        }

        return $mutationStatement;
    }

}
