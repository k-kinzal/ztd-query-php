<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;

/**
 * Follows SQLite's left-recursive lists without entering nested expressions.
 *
 * @visibility SqlSemantics
 */
final class SqliteLists
{
    /**
     * @return list<Node> Projection entries in reverse declaration order
     */
    public function projection(Node $select): array
    {
        $result = [];
        $list = Tree::child($select, ['selcollist']);
        while ($list !== null) {
            $result[] = $list;
            $prefix = Tree::child($list, ['sclp']);
            $list = $prefix === null ? null : Tree::child($prefix, ['selcollist']);
        }
        return $result;
    }
}
