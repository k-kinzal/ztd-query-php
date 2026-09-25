<?php

declare(strict_types=1);

namespace SqlFormatter\Compact;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Distinguishes expression grouping from row constructors, subqueries, and argument lists.
 *
 * @visibility SqlFormatter
 */
final class Grouping
{
    /**
     * Returns expression parentheses from the innermost group outward.
     *
     * @return list<array{int, int}>
     */
    public static function pairs(Node $tree): array
    {
        $pairs = [];
        foreach ($tree->children as $child) {
            if ($child instanceof Node) {
                array_push($pairs, ...self::pairs($child));
            }
        }
        $children = array_values(array_filter($tree->children, static fn (Node|Token $child): bool => $child instanceof Token || !$child->isEmpty()));
        if (count($children) === 3 && in_array($tree->name, ['expr', 'simple_expr', 'c_expr'], true)
            && $children[0] instanceof Token && $children[0]->text === '('
            && $children[1] instanceof Node && in_array($children[1]->name, ['expr', 'a_expr'], true)
            && $children[2] instanceof Token && $children[2]->text === ')') {
            $pairs[] = [$children[0]->offset, $children[2]->offset];
        }
        return $pairs;
    }
}
