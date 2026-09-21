<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\SemanticException;

/**
 * Navigates grammar boundaries without flattening nested query or expression scopes.
 *
 * @visibility SqlSemantics
 */
final class Tree
{
    /**
     * @param list<string> $nodes
     * @param list<string> $tokens
     */
    public static function assertChildren(Node $source, array $nodes, array $tokens): void
    {
        foreach (Tree::significant($source) as $child) {
            if ($child instanceof Node && !in_array($child->name, $nodes, true)) {
                Tree::invalid($child, 'clause');
            }
            if ($child instanceof Token && !in_array(strtoupper($child->text), $tokens, true)) {
                Tree::invalid($child, 'clause terminal');
            }
        }
    }


    /**
     * @param list<string> $names Nonterminals at which traversal stops
     * @return list<Node>
     */
    public static function outer(Node $node, array $names): array
    {
        if (in_array($node->name, $names, true)) {
            return [$node];
        }
        $found = [];
        foreach ($node->children as $child) {
            if ($child instanceof Node) {
                array_push($found, ...self::outer($child, $names));
            }
        }

        return $found;
    }

    /**
     * @param list<string> $names Names to find among immediate children
     */
    public static function child(Node $node, array $names): ?Node
    {
        foreach ($node->children as $child) {
            if ($child instanceof Node && in_array($child->name, $names, true) && $child->tokens() !== []) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<Node|Token>
     */
    public static function significant(Node $node): array
    {
        return array_values(array_filter($node->children, static fn (Node|Token $child): bool => $child instanceof Token ? $child->text !== '' : $child->tokens() !== []));
    }

    /**
     * Returns terminal spellings for diagnostics, without reparsing SQL.
     */
    public static function text(Node|Token $node): string
    {
        return $node instanceof Token ? $node->text : implode(' ', array_map(static fn (Token $token): string => $token->text, $node->tokens()));
    }

    /**
     * @throws SemanticException
     */
    public static function invalid(Node|Token $node, string $context): never
    {
        throw new SemanticException('invalid-structure', 'Cannot bind ' . $context . ': ' . self::text($node), $node);
    }
}
