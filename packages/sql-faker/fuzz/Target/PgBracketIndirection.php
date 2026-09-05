<?php

declare(strict_types=1);

namespace Fuzz\Target;

/**
 * Recognises the syntax error PostgreSQL raises for bracketed indirection.
 *
 * PostgreSQL only allows subscripting on an expression it can resolve to an
 * array, so the grammar can legitimately emit `a[1] b` shapes that the parser
 * rejects with `syntax error at or near "b"`. That rejection is a property of
 * the dialect rather than a grammar defect, and it is distinguished from a real
 * syntax error by checking that the token PostgreSQL complained about is the one
 * that directly follows a closing bracket in the statement.
 */
final class PgBracketIndirection
{
    /**
     * Checks whether a PostgreSQL syntax error is explained by bracketed indirection.
     *
     * @param string $sql Statement that PostgreSQL rejected
     * @param string $message Message PostgreSQL reported for the rejection
     *
     * @return bool True when the rejected token directly follows a closing bracket
     */
    public function explains(string $sql, string $message): bool
    {
        if (!str_contains($sql, '[')) {
            return false;
        }

        if (preg_match('/at or near "([^"]+)"/', $message, $matches) !== 1) {
            return false;
        }

        return preg_match('/\]\s+' . preg_quote($matches[1], '/') . '\b/', $sql) === 1;
    }
}
