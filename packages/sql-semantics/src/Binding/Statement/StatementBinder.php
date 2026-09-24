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
    public function bind(Node $tree, ?QueryContext $context = null, ?\SqlSemantics\Binding\Scope $parent = null): BoundStatement
    {
        $statements = StatementList::read($tree, $this->tables->identifiers->dialect);
        if (count($statements) !== 1) {
            throw new SemanticException('statement-count', 'bind() requires one statement; use bindAll() for a script.', $tree);
        }
        $statement = $this->node($tree, $statements[0], $context ?? new QueryContext($this->tables), $parent);
        if ($this->tables->diagnostics->items === []) {
            return $statement;
        }
        return $statement->withDiagnostics($this->tables->diagnostics->items);
    }

    /**
     * Binds a nested command without reparsing or losing its shared identity allocator.
     */
    public function node(Node $tree, Node $statement, QueryContext $context, ?\SqlSemantics\Binding\Scope $parent = null): BoundStatement
    {
        $snapshot = new QueryContext($context->tables, clone $context->ids, $context->ctes, parameterTypes: $context->parameterTypes);
        $validation = new \SqlSemantics\Binding\Editing\StatementContext($context->tables->schema, $snapshot, $parent);
        $operation = self::operation($statement);
        $select = Tree::child($statement, ['SelectStmt', 'select_stmt', 'select']);
        if (($operation === 'WITH' && ($select !== null || in_array($statement->name, ['select', 'SelectStmt', 'query_expression', 'select_stmt'], true))) || in_array($operation, ['SELECT', 'VALUES', 'TABLE', '('], true)) {
            $query = $context->bind($tree, $parent);
            return Retrieval\SelectIntoBinder::bind($tree, $statement, $query, $context)?->withContext($validation) ?? $query;
        }
        $mutation = Tree::outer($statement, ['InsertStmt', 'UpdateStmt', 'DeleteStmt', 'MergeStmt', 'insert_stmt', 'update_stmt', 'delete_stmt', 'replace_stmt'])[0] ?? null;
        if (in_array($operation, ['INSERT', 'REPLACE', 'UPDATE', 'DELETE', 'MERGE'], true)) {
            $context = (new \SqlSemantics\Binding\Query\QueryBinder($context))->with($tree, $parent);
            if ($operation === 'MERGE') {
                return (new \SqlSemantics\Binding\Write\MergeBinder())->bind($tree, $mutation ?? $statement, $context)->withContext($validation);
            }
            return (new MutationBinder($context, $parent))->bind($tree, $mutation ?? $statement)->withContext($validation);
        }
        return (new UtilityBinder($context))->bind($tree, $statement, $operation)->withContext($validation);
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
