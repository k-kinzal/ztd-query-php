<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

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
     * Reads named index keys, skipping expression keys and their nested identifiers.
     * Length prefixes, collations and sort directions are properties of a key.
     * @param list<Token> $tokens
     * @return list<string>
     */
    public static function keyNames(array $tokens, Identifiers $identifiers): array
    {
        $names = [];
        $depth = 0;
        $start = true;
        foreach ($tokens as $token) {
            if ($start) {
                if ($token->text !== '(') {
                    $names[] = $identifiers->name($token);
                }
                $start = false;
            }
            if ($token->text === '(') {
                ++$depth;
            } elseif ($token->text === ')') {
                --$depth;
            } elseif ($token->text === ',' && $depth === 0) {
                $start = true;
            }
        }
        return $names;
    }

    /**
     * Separates an optional constraint name from its integrity clause.
     * @param list<Token> $tokens
     * @return array{?string, list<Token>}
     */
    public static function constraintHeader(array $tokens, Identifiers $identifiers): array
    {
        $name = null;
        if (strtoupper($tokens[0]->text ?? '') === 'CONSTRAINT') {
            $unnamed = in_array(strtoupper($tokens[1]->text ?? ''), ['PRIMARY', 'UNIQUE', 'FOREIGN', 'CHECK'], true);
            $name = !$unnamed && isset($tokens[1]) ? $identifiers->name($tokens[1]) : null;
            $tokens = array_slice($tokens, $unnamed ? 1 : 2);
        }
        return [$name, $tokens];
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
