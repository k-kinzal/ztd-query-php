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
        $statement = $statements[0];
        $operation = self::operation($statement);
        $context = new QueryContext($this->tables);
        $select = Tree::child($statement, ['SelectStmt', 'select_stmt', 'select']);
        if (($select !== null && !in_array($operation, ['INSERT', 'REPLACE', 'CREATE'], true)) || in_array($operation, ['SELECT', 'VALUES', 'TABLE'], true)) {
            return $context->bind($tree);
        }
        $mutation = Tree::outer($statement, ['InsertStmt', 'UpdateStmt', 'DeleteStmt', 'MergeStmt', 'insert_stmt', 'update_stmt', 'delete_stmt', 'replace_stmt'])[0] ?? null;
        if ($mutation !== null || in_array($operation, ['INSERT', 'REPLACE', 'UPDATE', 'DELETE', 'MERGE'], true)) {
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
