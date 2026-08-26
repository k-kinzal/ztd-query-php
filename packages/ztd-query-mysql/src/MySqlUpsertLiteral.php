<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;

/**
 * Reads a literal or a name back out of the way MySQL writes it.
 *
 * A number may be written in hex, with a point, or with an exponent, and only
 * the first of those is an integer. A quoted string or a quoted name carries
 * doubled or escaped quotes that stand for one quote, and are not part of what
 * was written.
 */
final class MySqlUpsertLiteral
{
    /**
     * Answers the number a literal was written as.
     *
     * @param string $literal Number as the statement wrote it
     *
     * @return int|float The number, integral only where nothing said otherwise
     *
     * @throws UnsupportedSqlException When the text is not a number MySQL would write
     */
    public function numberOf(string $literal): int|float
    {
        $number = '/^(?:0x[0-9A-Fa-f]+|(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?)\z/';
        if (preg_match($number, $literal) !== 1) {
            throw new UnsupportedSqlException($literal, 'Unsupported UPSERT expression');
        }
        if (str_starts_with($literal, '0x')) {
            return intval($literal, 16);
        }

        return strpbrk($literal, '.eE') === false ? (int) $literal : (float) $literal;
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
        $inner = substr($literal, 1, -1);

        return str_replace(["''", "\\'", '\\\\'], ["'", "'", '\\'], $inner);
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

        $quote = $token->text[0] ?? '';
        $inner = substr($token->text, 1, -1);

        return str_replace($quote . $quote, $quote, $inner);
    }

    /**
     * Reports whether a token is a name at all.
     *
     * @param SqlToken $token Token to test
     *
     * @return bool True for a bare word or a quoted name
     */
    public function isName(SqlToken $token): bool
    {
        return $token->kind === SqlTokenKind::Word || $token->kind === SqlTokenKind::QuotedIdentifier;
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
