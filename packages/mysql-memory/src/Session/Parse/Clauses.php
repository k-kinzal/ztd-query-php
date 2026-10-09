<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Program\Activation;
use SqlParser\Lexer\Token;

/**
 * Finds in the tokens of a statement the clauses MySQL 5.6 and 5.7 refuse while they parse it, and where they refuse them.
 *
 * Those releases refuse INTO in the query of a view, and a variable or a parameter marker
 * written before it, where they parse INTO; INTO or LIMIT in a union operand but the last at the
 * UNION after it; a variable an INTO or a LIMIT names that no stored program declares where they
 * parse it; and a nested join at its closing parenthesis (verified on live 5.6.51 and 5.7.44
 * servers).
 * Source: https://dev.mysql.com/doc/refman/5.7/en/union.html,
 * https://dev.mysql.com/doc/refman/5.7/en/create-view.html.
 *
 * @visibility MySqlMemory
 */
final class Clauses
{
    /**
     * Answers the offset of INTO in the query of a view, where MySQL 5.6 and 5.7 refuse it (ER_VIEW_SELECT_CLAUSE), or null for another statement or a query without INTO.
     *
     * @param list<Token> $tokens
     */
    public function viewed(array $tokens): ?int
    {
        $view = false;
        foreach ($tokens as $token) {
            if ($token->name === 'SELECT_SYM' && !$view) {
                return null;
            }
            $view = $view || $token->name === 'VIEW_SYM';
            if ($view && $token->name === 'INTO') {
                return $token->offset;
            }
        }

        return null;
    }

    /**
     * Answers whether the query of a statement writes a variable or a parameter marker before an offset, which MySQL 5.6 and 5.7 refuse in a view where they parse it (ER_VIEW_SELECT_VARIABLE).
     *
     * @param list<Token> $tokens
     */
    public function marked(array $tokens, int $end): bool
    {
        $query = false;
        foreach ($tokens as $token) {
            if ($token->offset >= $end) {
                return false;
            }
            $query = $query || $token->name === 'SELECT_SYM';
            if ($query && ($token->text === '@' || $token->name === 'PARAM_MARKER')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the offset of the first UNION after a clause, INTO by default, where MySQL 5.6 and 5.7 refuse the clause in a union operand but the last, or the end of the tokens.
     *
     * @param list<Token> $tokens
     * @param string $clause The token name of the clause: INTO or LIMIT
     */
    public function united(array $tokens, string $clause = 'INTO'): int
    {
        $into = false;
        foreach ($tokens as $token) {
            if ($into && $token->name === 'UNION_SYM') {
                return $token->offset;
            }
            $into = $into || $token->name === $clause;
        }

        return PHP_INT_MAX;
    }

    /**
     * Answers the first variable an INTO of a query or a LIMIT written before an offset names that no running stored program declares, which MySQL 5.6 and 5.7 refuse before a later refusal of the statement (verified on live 5.6.51 and 5.7.44 servers), or null.
     *
     * @param list<Token> $tokens
     */
    public function unknown(array $tokens, int $end, ?Activation $program): ?string
    {
        $clause = null;
        $previous = null;
        $selected = false;
        foreach ($tokens as $token) {
            if ($token->offset >= $end) {
                return null;
            }
            $selected = $selected || $token->name === 'SELECT_SYM';
            $clause = $this->clause($clause, $token, $previous, $selected);
            $name = str_starts_with($token->text, '`') ? str_replace('``', '`', substr($token->text, 1, -1)) : $token->text;
            if ($clause !== null && in_array($token->name, ['IDENT', 'IDENT_QUOTED'], true) && $previous?->text !== '@' && $program?->variable($name) === null) {
                return $name;
            }
            $previous = $token;
        }

        return null;
    }

    /**
     * Answers the clause naming variables a token is part of: INTO after a SELECT, or LIMIT, with the names, commas, user variables, numbers and parameter markers they write; null outside them.
     *
     * @param string|null $clause The clause the token before is part of
     * @param Token|null $previous The token before
     * @param bool $selected Whether a SELECT was read
     */
    public function clause(?string $clause, Token $token, ?Token $previous, bool $selected): ?string
    {
        $named = in_array($token->name, ['IDENT', 'IDENT_QUOTED'], true);

        return match (true) {
            $token->name === 'INTO' && $selected => 'INTO',
            $token->name === 'LIMIT' => 'LIMIT',
            $clause === 'INTO' && ($named || in_array($token->text, [',', '@'], true) || $previous?->text === '@') => 'INTO',
            $clause === 'LIMIT' && ($named || in_array(strtoupper($token->text), [',', 'OFFSET'], true) || in_array($token->name, ['NUM', 'LONG_NUM', 'ULONGLONG_NUM', 'DECIMAL_NUM', 'PARAM_MARKER'], true)) => 'LIMIT',
            default => null,
        };
    }

    /**
     * Answers the offset of the closing parenthesis of the innermost parentheses around an offset, or the offset itself outside parentheses: where MySQL 5.7 refuses the nested join a refusal reported at the offset belongs to.
     *
     * @param list<Token> $tokens
     */
    public function closing(array $tokens, int $offset): int
    {
        $open = [];
        foreach ($tokens as $token) {
            if ($token->text === '(') {
                $open[] = $token->offset;
            } elseif ($token->text === ')') {
                $start = array_pop($open);
                if ($start !== null && $start < $offset && $token->offset > $offset) {
                    return $token->offset;
                }
            }
        }

        return $offset;
    }
}
