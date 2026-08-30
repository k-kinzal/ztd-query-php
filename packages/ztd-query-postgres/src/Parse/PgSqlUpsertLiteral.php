<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Parse;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;

/**
 * Reads a literal or a name back out of the way PostgreSQL writes it.
 *
 * A number may be written with underscores between its digits, with a point,
 * or with an exponent; only the first of those is still an integer. A quoted
 * string or a quoted name carries doubled quotes that stand for one quote,
 * and are not part of what was written.
 *
 * PostgreSQL quotes a name with double quotes and nothing else, so a token
 * quoted any other way is not a name here even though another dialect's
 * reader would have taken it for one.
 */
final class PgSqlUpsertLiteral
{
    /**
     * Answers the number a literal was written as.
     *
     * @param string $literal Number as the statement wrote it
     *
     * @return int|float The number, integral only where nothing said otherwise
     *
     * @throws UnsupportedSqlException When the text is not a number PostgreSQL would write
     */
    public function numberOf(string $literal): int|float
    {
        $digits = str_replace('_', '', $literal);
        $number = '/^(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z/';
        if (preg_match($number, $digits) !== 1) {
            throw new UnsupportedSqlException($literal, 'Unsupported UPSERT expression');
        }

        return strpbrk($digits, '.eE') === false ? (int) $digits : (float) $digits;
    }

    /**
     * Answers the text a string literal stands for.
     *
     * @param string $literal String as the statement wrote it, quotes and all
     *
     * @return string The text it stands for
     */
    public function textOf(string $literal): string
    {
        return str_replace("''", "'", substr($literal, 1, -1));
    }

    /**
     * Answers the name a token stands for.
     *
     * @param SqlToken $token Token the name was written as
     *
     * @return string The name, with the quoting taken off
     */
    public function nameOf(SqlToken $token): string
    {
        if ($token->kind === SqlTokenKind::Word) {
            return $token->text;
        }

        return str_replace('""', '"', substr($token->text, 1, -1));
    }

    /**
     * Reports whether a token is a name at all.
     *
     * @param SqlToken $token Token to test
     *
     * @return bool True for a bare word, or a name quoted the way PostgreSQL quotes one
     */
    public function isName(SqlToken $token): bool
    {
        if ($token->kind === SqlTokenKind::Word) {
            return true;
        }

        return $token->kind === SqlTokenKind::QuotedIdentifier && str_starts_with($token->text, '"');
    }

    /**
     * Reports whether a token is one of these symbols.
     *
     * @param SqlToken $token Token to test
     * @param list<string> $symbols Symbols it may be
     *
     * @return bool True when the token is one of them
     */
    public function isSymbol(SqlToken $token, array $symbols): bool
    {
        return $token->kind === SqlTokenKind::Symbol && in_array($token->text, $symbols, true);
    }
}
