<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\IdentitySequence;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\BoundStatement;

/**
 * Shares identities and visible common table expressions across nested scopes.
 *
 * @visibility SqlSemantics
 */
final class QueryContext
{
    /**
     * @param array<string, BoundStatement> $ctes Visible CTE declarations
     */
    public function __construct(
        public readonly TableResolver $tables,
        public readonly IdentitySequence $ids = new IdentitySequence(),
        public readonly array $ctes = [],
    ) {
    }

    /**
     * Binds a nested query with its explicit correlation namespace.
     */
    public function bind(Node $node, ?Scope $parent = null): BoundSelect
    {
        return (new QueryBinder($this))->bind($node, $parent);
    }
}
