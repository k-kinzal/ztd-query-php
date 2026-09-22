<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;

/**
 * Flattens a left-recursive list without entering its expression operands.
 *
 * @visibility SqlSemantics
 */
final class OrderingNodes
{
    /**
     * @return non-empty-list<Node>
     */
    public static function read(Node $list): array
    {
        $prefix = Tree::child($list, [$list->name]);
        return [...($prefix === null ? [] : self::read($prefix)), $list];
    }
}
