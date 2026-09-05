<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

/**
 * Encodes chosen text using MySQL quoting rules, independently of generation.
 *
 * @visibility root
 */
final class MySqlQuoting
{
    /**
     * Quotes an identifier and escapes embedded delimiters.
     */
    public static function identifier(string $body): string
    {
        return '`' . str_replace('`', '``', $body) . '`';
    }

    /**
     * Quotes a string body and doubles embedded single quotes.
     */
    public static function stringLiteral(string $body): string
    {
        return "'" . str_replace("'", "''", $body) . "'";
    }
}
