<?php

declare(strict_types=1);

namespace SqlFormatter\Compact;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Applies canonical spellings and optional grammar rules to a lossless syntax tree.
 *
 * @visibility SqlFormatter
 */
final class Visitor
{
    /**
     * @param array<int, Token> $cleaned Tokens with ordinary trivia removed
     * @param array<int, true> $protected Tokens carrying directives or executable SQL
     * @param array<int, true> $verbatim Tokens inside executable comments
     */
    public function __construct(private readonly array $cleaned, private readonly array $protected, private readonly array $verbatim, private readonly bool $mysql)
    {
    }

    /**
     * @return list<Token>
     */
    public function tokens(Node|Token $node, bool $identifier = false, ?Node $parent = null): array
    {
        if ($node instanceof Token) {
            if ($node->text === '') {
                return [];
            }
            $token = $this->cleaned[$node->offset];
            $text = isset($this->verbatim[$node->offset]) ? $node->text : Keywords::text($node, $identifier || (!$this->mysql && Keywords::fallback($node, $parent)), $this->mysql);
            return [new Token($token->symbol, $token->name, $text, $token->offset, $token->leading)];
        }
        $identifier = $identifier || (Keywords::identifier($node->name) && $parent?->name !== 'joinop');
        $tokens = [];
        foreach ($node->children as $child) {
            array_push($tokens, ...$this->tokens($child, $identifier, $node));
        }
        foreach ($tokens as $token) {
            if (isset($this->protected[$token->offset])) {
                return $tokens;
            }
        }
        return $identifier ? $tokens : Rules::apply($node, $tokens, $parent);
    }
}
