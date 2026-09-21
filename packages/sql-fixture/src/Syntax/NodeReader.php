<?php

declare(strict_types=1);

namespace SqlFixture\Syntax;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Reads the direct children and token shapes of a syntax tree node.
 *
 * @visibility root
 */
final class NodeReader
{
    /**
     * Returns the first direct child node with the given grammar name.
     */
    public function child(Node $node, string $name): ?Node
    {
        foreach ($node->children as $child) {
            if ($child instanceof Node && $child->name === $name) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Returns the first direct child token with the given terminal name.
     */
    public function token(Node $node, string $name): ?Token
    {
        foreach ($node->children as $child) {
            if ($child instanceof Token && $child->is($name)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Returns the first token under the node, in text order.
     */
    public function firstToken(Node $node): ?Token
    {
        foreach ($node->tokens() as $token) {
            return $token;
        }

        return null;
    }

    /**
     * Reports whether any token under the node is the given terminal.
     */
    public function containsToken(Node $node, string $name): bool
    {
        foreach ($node->tokens() as $token) {
            if ($token->is($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the token texts that sit outside every parenthesised group under the node.
     *
     * @return list<string>
     */
    public function wordsOutsideParentheses(Node $node): array
    {
        $words = [];
        $depth = 0;
        foreach ($node->tokens() as $token) {
            if ($token->text === '(') {
                $depth++;
                continue;
            }
            if ($token->text === ')') {
                $depth--;
                continue;
            }
            if ($depth === 0 && $token->text !== '') {
                $words[] = $token->text;
            }
        }

        return $words;
    }

    /**
     * Returns the SQL text from the first through the last of the given tokens.
     *
     * @param list<Token> $tokens
     */
    public function textOf(array $tokens, string $source): string
    {
        $first = $tokens[0] ?? null;
        $last = $tokens[count($tokens) - 1] ?? null;
        if ($first === null || $last === null) {
            return '';
        }

        return substr($source, $first->offset, $last->end() - $first->offset);
    }
}
