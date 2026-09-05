<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

/**
 * Encodes chosen text using SQLite quoting rules, independently of generation.
 *
 * @visibility root
 */
final class SqliteQuoting
{
    /**
     * Quotes an identifier and escapes embedded delimiters.
     */
    public static function identifier(string $body): string
    {
        return '"' . str_replace('"', '""', $body) . '"';
    }

    /**
     * Quotes a string body and doubles embedded single quotes.
     */
    public static function stringLiteral(string $body): string
    {
        return "'" . str_replace("'", "''", $body) . "'";
    }

    /**
     * Quotes an identifier with backticks and doubles embedded backticks.
     */
    public static function backtickIdentifier(string $body): string
    {
        return '`' . str_replace('`', '``', $body) . '`';
    }

    /**
     * Encloses an identifier in brackets, removing unrepresentable closing brackets.
     */
    public static function bracketIdentifier(string $body): string
    {
        return '[' . str_replace(']', '', $body) . ']';
    }
}
