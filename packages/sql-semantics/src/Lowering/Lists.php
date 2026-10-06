<?php

declare(strict_types=1);

namespace SqlSemantics\Lowering;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Flattens the recursive list productions of a grammar without recursing on the list spine.
 *
 * @visibility SqlSemantics
 */
final class Lists
{
    /**
     * Answers the children along a left- or right-recursive list, in source order.
     *
     * A child that has the same rule name as the list is the continuation of
     * the list and is expanded in place; every other child, separators
     * included, is returned.
     *
     * @return list<Node|Token>
     */
    public function elements(Node $list): array
    {
        $elements = [];
        $pending = [$list];
        while ($pending !== []) {
            $current = array_pop($pending);
            if ($current instanceof Node && $current->name === $list->name) {
                for ($index = count($current->children) - 1; $index >= 0; $index--) {
                    $pending[] = $current->children[$index];
                }
            } else {
                $elements[] = $current;
            }
        }

        return $elements;
    }

    /**
     * Answers the nonterminal items of a recursive list, dropping separator tokens.
     *
     * @return list<Node>
     */
    public function items(Node $list): array
    {
        $items = [];
        foreach ($this->elements($list) as $element) {
            if ($element instanceof Node) {
                $items[] = $element;
            }
        }

        return $items;
    }
}
