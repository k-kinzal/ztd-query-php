<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use SqlParser\Lexer\Token;

/**
 * Reads balanced declaration groups from tokens already classified by sql-parser.
 *
 * @visibility SqlSemantics
 */
final class TokenGroups
{
    /**
     * @param list<Token> $tokens
     * @return list<list<Token>>
     */
    public static function parentheses(array $tokens): array
    {
        $groups = [];
        $current = [];
        $depth = 0;
        foreach ($tokens as $token) {
            if ($token->text === '(') {
                ++$depth;
                if ($depth === 1) {
                    $current = [];
                    continue;
                }
            }
            if ($token->text === ')') {
                --$depth;
                if ($depth === 0) {
                    $groups[] = $current;
                    continue;
                }
            }
            if ($depth > 0) {
                $current[] = $token;
            }
        }

        return $groups;
    }

    /**
     * @param list<Token> $tokens
     * @return list<string>
     */
    public static function names(array $tokens, Identifiers $identifiers): array
    {
        return array_values(array_map($identifiers->name(...), array_filter($tokens, static fn (Token $token): bool => !in_array($token->text, [',', '.'], true))));
    }
}
