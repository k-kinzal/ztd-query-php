<?php

declare(strict_types=1);

namespace SqlFormatter\Core\Compact;

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
    public function __construct(private readonly array $cleaned, private readonly array $protected, private readonly array $verbatim, private readonly Settings $settings)
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
            $text = isset($this->verbatim[$node->offset]) ? $node->text : $this->settings->keywords->text($node, $identifier || $this->settings->keywords->fallback($node, $parent));
            return [new Token($token->symbol, $token->name, $text, $token->offset, $token->leading)];
        }
        $identifier = $identifier || $this->settings->keywords->identifier($node->name, $parent);
        $tokens = [];
        foreach ($node->children as $child) {
            array_push($tokens, ...$this->tokens($child, $identifier, $node));
        }
        foreach ($tokens as $token) {
            if (isset($this->protected[$token->offset])) {
                return $tokens;
            }
        }
        return $identifier ? $tokens : $this->settings->rules->apply($node, $tokens, $parent);
    }
}
