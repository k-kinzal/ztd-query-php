<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\StatementList;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Model\BoundSelect;

/**
 * Builds a logical SELECT with separate matching, filtering, and projection stages.
 *
 * @visibility SqlSemantics
 */
final class SelectBinder
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly TableResolver $tables)
    {
    }

    /**
     * Binds one SELECT and retains its row and value dependencies.
     *
     * @throws \SqlSemantics\SemanticException When the statement cannot be bound
     */
    public function bind(Node $tree): BoundSelect
    {
        $statements = StatementList::read($tree, $this->tables->identifiers->dialect);
        if (count($statements) !== 1) {
            Tree::unsupported($tree, 'multiple query statements');
        }
        $statement = $statements[0];
        Tree::assertChildren($statement, ['SelectStmt', 'select_stmt', 'select'], []);
        $selects = [...$statement->find('simple_select'), ...$statement->find('query_specification'), ...$statement->find('oneselect')];
        if (count($selects) !== 1) {
            Tree::unsupported($statement, 'query shape');
        }
        $select = $selects[0];
        SyntaxGuard::select($statement, $select);
        $fromNode = Tree::outer($select, ['from_clause', 'from'])[0] ?? null;
        $from = $fromNode === null ? null : (new FromBinder($this->tables, new IdentitySequence()))->bind($fromNode);
        $scope = $from->scope ?? new Scope($this->tables->identifiers);
        $whereNode = Tree::outer($select, ['where_clause', 'where_opt'])[0] ?? null;
        $predicateNode = $whereNode === null ? null : Tree::child($whereNode, ['a_expr', 'expr']);
        $predicate = $predicateNode === null ? null : (new ExpressionBinder())->bind($predicateNode, $scope);
        if ($predicate !== null) {
            (new ExpressionRules($this->tables->identifiers->dialect))->predicate($predicate);
        }
        $outputs = (new ProjectionBinder())->bind($select, $scope);
        $distinct = false;
        foreach (Tree::outer($select, ['distinct_clause', 'select_options', 'distinct']) as $options) {
            $distinct = $distinct || strtoupper(Tree::text($options)) === 'DISTINCT';
        }
        $tail = new SelectModifiersBinder();
        [$limit, $offset] = $tail->pagination($statement, new Scope($this->tables->identifiers));

        return new BoundSelect('s0', $from?->relation, $scope->relations, $outputs, $predicate, $distinct, $tail->ordering($statement, $scope, $outputs), $limit, $offset, $tree);
    }
}
