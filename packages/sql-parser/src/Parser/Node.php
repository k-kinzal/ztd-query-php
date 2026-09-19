<?php

declare(strict_types=1);

namespace SqlParser\Parser;

use SqlParser\Lexer\Token;

/**
 * One nonterminal of the syntax tree, named after the grammar rule that built it.
 *
 * The tree is concrete: every token of the SQL text is a leaf, and the
 * nonterminals are those of the official grammar, so a node is named after
 * the rule of the upstream grammar file that built it. The ordinal says
 * which alternative of the rule matched.
 *
 * @visibility public
 *
 * @example Reading the text a node covers
 *     $sql = 'SELECT 1';
 *     $select = new \SqlParser\Lexer\Token(3, 'SELECT', 'SELECT', 0);
 *     $one = new \SqlParser\Lexer\Token(7, 'NUM', '1', 7);
 *     $tree = new \SqlParser\Parser\Node('statement', 0, [$select, new \SqlParser\Parser\Node('expr', 2, [$one])]);
 *     $tree->find('expr')[0]->text($sql) // => '1'
 *     $tree->text($sql) // => 'SELECT 1'
 */
final class Node
{
    /**
     * @param string $name Nonterminal name as the grammar spells it
     * @param int $ordinal Which alternative of the nonterminal matched, counted from zero
     * @param list<Node|Token> $children What the alternative matched, in order
     */
    public function __construct(
        public readonly string $name,
        public readonly int $ordinal,
        public readonly array $children,
    ) {
    }

    /**
     * Reports whether the node matched nothing at all.
     *
     * @return bool True for an empty alternative
     */
    public function isEmpty(): bool
    {
        return $this->children === [];
    }

    /**
     * Answers every token under the node, in text order.
     *
     * @return list<Token> The tokens
     */
    public function tokens(): array
    {
        $tokens = [];
        foreach ($this->children as $child) {
            if ($child instanceof Token) {
                $tokens[] = $child;
            } else {
                array_push($tokens, ...$child->tokens());
            }
        }

        return $tokens;
    }

    /**
     * Answers the nodes under this one with a given name, this one included, in text order.
     *
     * @param string $name Nonterminal name
     *
     * @return list<Node> The matching nodes
     */
    public function find(string $name): array
    {
        $found = $this->name === $name ? [$this] : [];
        foreach ($this->children as $child) {
            if ($child instanceof self) {
                array_push($found, ...$child->find($name));
            }
        }

        return $found;
    }

    /**
     * Answers the byte range of the text the node covers.
     *
     * @return array{int, int}|null Start offset and end offset, or null for an empty node
     */
    public function span(): ?array
    {
        $tokens = $this->tokens();
        if ($tokens === []) {
            return null;
        }

        return [$tokens[0]->offset, $tokens[count($tokens) - 1]->end()];
    }

    /**
     * Answers the SQL text the node covers.
     *
     * @param string $source The SQL text the tree was parsed from
     *
     * @return string The covered text, empty for an empty node
     */
    public function text(string $source): string
    {
        $span = $this->span();

        return $span === null ? '' : substr($source, $span[0], $span[1] - $span[0]);
    }
}
