<?php

declare(strict_types=1);

namespace SqlFormatter\Core\Compact;

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
     * @param array<string, list<string>> $rules Parenthesis owner to expression children
     */
    public function __construct(private readonly array $rules)
    {
    }

    /**
     * Returns expression parentheses from the innermost group outward.
     *
     * @return list<array{int, int}>
     */
    public function pairs(Node $tree): array
    {
        $pairs = [];
        foreach ($tree->children as $child) {
            if ($child instanceof Node) {
                array_push($pairs, ...$this->pairs($child));
            }
        }
        $children = array_values(array_filter($tree->children, static fn (Node|Token $child): bool => $child instanceof Token || !$child->isEmpty()));
        if (count($children) === 3 && isset($this->rules[$tree->name])
            && $children[0] instanceof Token && $children[0]->text === '('
            && $children[1] instanceof Node && in_array($children[1]->name, $this->rules[$tree->name], true)
            && $children[2] instanceof Token && $children[2]->text === ')') {
            $pairs[] = [$children[0]->offset, $children[2]->offset];
        }
        return $pairs;
    }
}
