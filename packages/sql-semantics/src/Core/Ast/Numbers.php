<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Lexer\Token;

/**
 * Reads declared numeric arguments without floating-point or overflow surprises.
 *
 * @visibility SqlSemantics
 */
final class Numbers
{
    /**
     * Reads an optionally signed decimal integer spelled by consecutive tokens, or null when the tokens spell something else or overflow.
     *
     * @param list<Token> $tokens
     */
    public static function integer(array $tokens): ?int
    {
        $sign = 1;
        if (count($tokens) === 2 && in_array($tokens[0]->text, ['+', '-'], true)) {
            $sign = $tokens[0]->text === '-' ? -1 : 1;
            $tokens = [$tokens[1]];
        }
        if (count($tokens) !== 1) {
            return null;
        }
        $digits = str_replace('_', '', $tokens[0]->text);
        if ($digits === '' || !ctype_digit($digits)) {
            return null;
        }
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }
        $limit = $sign < 0 ? '9223372036854775808' : '9223372036854775807';
        if (strlen($digits) > 19 || (strlen($digits) === 19 && strcmp($digits, $limit) > 0)) {
            return null;
        }

        return $sign < 0 && $digits === '9223372036854775808' ? PHP_INT_MIN : $sign * (int) $digits;
    }

    /**
     * Splits the tokens of one parenthesized argument list at its top-level commas.
     *
     * @param list<Token> $tokens Tokens inside the parentheses, without them
     * @return list<list<Token>>
     */
    public static function arguments(array $tokens): array
    {
        $arguments = [];
        $current = [];
        $depth = 0;
        foreach ($tokens as $token) {
            if ($token->text === ',' && $depth === 0) {
                $arguments[] = $current;
                $current = [];
                continue;
            }
            $depth += $token->text === '(' ? 1 : ($token->text === ')' ? -1 : 0);
            $current[] = $token;
        }
        if ($current !== [] || $arguments !== []) {
            $arguments[] = $current;
        }

        return $arguments;
    }
}
