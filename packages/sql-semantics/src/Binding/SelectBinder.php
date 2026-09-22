<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\StatementList;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Model\BoundQuery;

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
    public function bind(Node $tree): BoundQuery
    {
        $statements = StatementList::read($tree, $this->tables->identifiers->dialect);
        if (count($statements) !== 1) {
            Tree::invalid($tree, 'multiple query statements');
        }
        return (new Query\QueryContext($this->tables))->bind($tree);
    }
}
