<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\StatementList;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Model\BoundSelect;

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
     * @throws \SqlSemantics\Core\SemanticException When the statement cannot be bound
     */
    public function bind(Node $tree): BoundSelect
    {
        $statements = StatementList::read($tree, $this->tables->identifiers->dialect);
        if (count($statements) !== 1) {
            Tree::unsupported($tree, 'multiple query statements');
        }
        $statement = $statements[0];
        Tree::assertChildren($statement, $this->tables->identifiers->dialect->platform()->syntax()->nodes('selectStatement'), []);
        $selects = array_merge(...array_map($statement->find(...), $this->tables->identifiers->dialect->platform()->syntax()->nodes('selectBody')));
        if (count($selects) !== 1) {
            Tree::unsupported($statement, 'query shape');
        }
        $select = $selects[0];
        SyntaxGuard::select($statement, $select, $this->tables->identifiers->dialect->platform()->syntax());
        $fromNode = Tree::outer($select, $this->tables->identifiers->dialect->platform()->syntax()->nodes('from'))[0] ?? null;
        $from = $fromNode === null ? null : (new FromBinder($this->tables, new IdentitySequence()))->bind($fromNode);
        $scope = $from->scope ?? new Scope($this->tables->identifiers);
        $whereNode = Tree::outer($select, $this->tables->identifiers->dialect->platform()->syntax()->nodes('where'))[0] ?? null;
        $predicateNode = $whereNode === null ? null : Tree::child($whereNode, $this->tables->identifiers->dialect->platform()->syntax()->nodes('expression'));
        $predicate = $predicateNode === null ? null : (new ExpressionBinder())->bind($predicateNode, $scope);
        if ($predicate !== null) {
            (new ExpressionRules($this->tables->identifiers->dialect))->predicate($predicate);
        }
        $outputs = (new ProjectionBinder())->bind($select, $scope);
        $distinct = false;
        foreach (Tree::outer($select, $this->tables->identifiers->dialect->platform()->syntax()->nodes('selectOptions')) as $options) {
            $distinct = $distinct || strtoupper(Tree::text($options)) === 'DISTINCT';
        }
        $tail = new SelectModifiersBinder();
        [$limit, $offset] = $tail->pagination($statement, new Scope($this->tables->identifiers));

        return new BoundSelect('s0', $from?->relation, $scope->relations, $outputs, $predicate, $distinct, $tail->ordering($statement, $scope, $outputs), $limit, $offset, $tree);
    }
}
