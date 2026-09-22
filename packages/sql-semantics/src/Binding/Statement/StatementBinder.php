<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\StatementList;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\SemanticException;

/**
 * Dispatches every grammar statement by its semantic operation, without a SQL allowlist.
 *
 * @visibility SqlSemantics
 */
final class StatementBinder
{
    /**
     * Uses a declared schema for resolving data dependencies.
     */
    public function __construct(public readonly TableResolver $tables)
    {
    }

    /**
     * Binds one statement; transactions and administrative commands retain their syntax.
     *
     * @throws SemanticException
     */
    public function bind(Node $tree): BoundStatement
    {
        $statements = StatementList::read($tree, $this->tables->identifiers->dialect);
        if (count($statements) !== 1) {
            throw new SemanticException('statement-count', 'bind() requires one statement; use bindAll() for a script.', $tree);
        }
        $statement = $this->node($tree, $statements[0], new QueryContext($this->tables));
        if ($this->tables->diagnostics->items === []) {
            return $statement;
        }
        return new ($statement::class)(
            scopeId: $statement->scopeId,
            from: $statement->from,
            relations: $statement->relations,
            outputs: $statement->outputs,
            where: $statement->where,
            distinct: $statement->distinct,
            orderBy: $statement->orderBy,
            limit: $statement->limit,
            offset: $statement->offset,
            source: $statement->source,
            groupBy: $statement->groupBy,
            having: $statement->having,
            ctes: $statement->ctes,
            branches: $statement->branches,
            setOperator: $statement->setOperator,
            clauses: $statement->clauses,
            kind: $statement->kind,
            targets: $statement->targets,
            assignments: $statement->assignments,
            queries: $statement->queries,
            rows: $statement->rows,
            withTies: $statement->withTies,
            syntaxClauses: $statement->syntaxClauses,
            declarations: $statement->declarations,
            insertion: $statement->insertion,
            writes: $statement->writes,
            settings: $statement->settings,
            conflicts: $statement->conflicts,
            definitions: $statement->definitions,
            merge: $statement->merge,
            statements: $statement->statements,
            diagnostics: $this->tables->diagnostics->items,
            indexes: $statement->indexes,
        );
    }

    /**
     * Binds a nested command without reparsing or losing its shared identity allocator.
     */
    public function node(Node $tree, Node $statement, QueryContext $context): BoundStatement
    {
        $operation = self::operation($statement);
        $select = Tree::child($statement, ['SelectStmt', 'select_stmt', 'select']);
        if (($operation === 'WITH' && $select !== null) || in_array($operation, ['SELECT', 'VALUES', 'TABLE', '('], true)) {
            return $context->bind($tree);
        }
        $mutation = Tree::outer($statement, ['InsertStmt', 'UpdateStmt', 'DeleteStmt', 'MergeStmt', 'insert_stmt', 'update_stmt', 'delete_stmt', 'replace_stmt'])[0] ?? null;
        if (in_array($operation, ['INSERT', 'REPLACE', 'UPDATE', 'DELETE', 'MERGE'], true)) {
            $context = (new \SqlSemantics\Binding\Query\QueryBinder($context))->with($tree, null);
            if ($operation === 'MERGE') {
                return (new \SqlSemantics\Binding\Write\MergeBinder())->bind($tree, $mutation ?? $statement, $context);
            }
            return (new MutationBinder($context))->bind($tree, $mutation ?? $statement);
        }
        return (new UtilityBinder($context))->bind($tree, $statement, $operation);
    }
    /**
     * Reads the outer command verb without entering its WITH declarations.
     */
    public static function operation(Node $statement): string
    {
        foreach (Tree::significant($statement) as $child) {
            if ($child instanceof Node && in_array($child->name, ['with_clause', 'with', 'wqlist'], true)) {
                continue;
            }
            $operation = $child instanceof Node ? self::operation($child) : strtoupper($child->text);
            if ($operation !== '') {
                return $operation;
            }
        }
        return '';
    }

}
