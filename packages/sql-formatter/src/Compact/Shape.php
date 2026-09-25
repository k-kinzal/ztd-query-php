<?php

declare(strict_types=1);

namespace SqlFormatter\Compact;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Compares canonical syntax while retaining operand nesting and operator association.
 *
 * @visibility SqlFormatter
 */
final class Shape
{
    /**
     * @param array<int, Token> $tokens Canonical tokens indexed by original offset
     * @param array<int, true> $grouping Expression parentheses excluded from the shape
     */
    public function __construct(private readonly array $tokens, private readonly array $grouping)
    {
    }

    /**
     * Collapses empty and single-child grammar wrappers, but preserves branching.
     */
    public function of(Node|Token $node): ?string
    {
        if ($node instanceof Token) {
            $token = $this->tokens[$node->offset] ?? null;
            if ($node->text === '' || $token === null || isset($this->grouping[$node->offset])) {
                return null;
            }
            return hash('sha256', serialize([$token->name, $token->text, $token->leading]));
        }
        $children = [];
        foreach ($node->children as $child) {
            $signature = $this->of($child);
            if ($signature !== null) {
                $children[] = $signature;
            }
        }
        return match (count($children)) {
            0 => null,
            1 => $children[0],
            default => hash('sha256', serialize([$node->name, $children])),
        };
    }
}
