<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use SqlParser\Parser\Node;

/**
 * Associates SQLite constraint-name productions with their following bodies.
 *
 * @visibility SqlSemantics
 */
final class ConstraintGroups
{
    /**
     * @param list<Node> $nodes
     * @return list<Node>
     */
    public function read(array $nodes): array
    {
        $result = [];
        $pending = null;
        foreach ($nodes as $node) {
            if (count($node->tokens()) === 2 && strtoupper($node->tokens()[0]->text) === 'CONSTRAINT') {
                $pending = $node;
                continue;
            }
            $result[] = $pending === null ? $node : new Node($node->name, $node->ordinal, [$pending, $node]);
            $pending = null;
        }
        return $result;
    }
}
