<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use SqlParser\Parser\Node;

/**
 * Associates SQLite constraint-name productions with their following bodies, and
 * MySQL column-level [NOT] ENFORCED attributes with the CHECK they qualify.
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
            $text = strtoupper(Tree::text($node));
            if ($result !== [] && (str_starts_with($text, 'DEFERRABLE') || str_starts_with($text, 'NOT DEFERRABLE') || str_starts_with($text, 'INITIALLY '))) {
                $previous = array_pop($result);
                $result[] = new Node($previous->name, $previous->ordinal, [$previous, $node]);
                continue;
            }
            if ($result !== [] && Tree::child($node, ['constraint_enforcement']) !== null && Tree::outer($result[count($result) - 1], ['check_constraint']) !== []) {
                $previous = array_pop($result);
                $result[] = new Node($previous->name, $previous->ordinal, [$previous, $node]);
                continue;
            }
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
