<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Binding\BoundRelation;
use SqlSemantics\Core\Binding\FromBinder;

/**
 * Supplies query syntax interpretation.
 *
 * @visibility SqlSemantics
 */
interface QueryRules
{
    /**
     * @return list<Node>
     */
    public function projectionItems(Node $select): array;

    /**
     * @return list<Token>
     */
    public function projectionTokens(Node $item, ?Node $expression): array;

    /**
     * @return list<Node>
     */
    public function orderingNodes(Node $statement): array;

    /**
     * Interprets one table reference or join using the core relation binder.
     */
    public function relation(Node $node, FromBinder $binder): BoundRelation;
}
