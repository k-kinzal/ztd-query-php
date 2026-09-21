<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Model\BoundStatement;

/**
 * Preserves utility operations and binds their embedded query dependencies.
 *
 * @visibility SqlSemantics
 */
final class UtilityBinder
{
    /**
     * Resolves embedded queries in the caller's semantic environment.
     */
    public function __construct(public readonly QueryContext $context)
    {
    }

    /**
     * Binds DDL, transaction, maintenance, and administrative grammar statements.
     */
    public function bind(Node $source, Node $statement, string $kind): BoundStatement
    {
        $queries = [];
        foreach (Tree::outer($statement, ['SelectStmt', 'select_stmt', 'query_expression', 'select']) as $node) {
            $queries[] = $this->context->bind($node);
        }
        return new BoundStatement($this->context->ids->scope(), null, [], [], null, false, [], null, null, $source, kind: $kind, queries: $queries);
    }
}
