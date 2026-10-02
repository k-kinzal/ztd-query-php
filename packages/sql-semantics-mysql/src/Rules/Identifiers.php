<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules;

/**
 * Decodes the spelling of a MySQL identifier token.
 *
 * Rule: MYSQL-IDENTIFIER-DECODE-001. Scope: the terminals IDENT and
 * IDENT_QUOTED of every release. A word in backticks is the text between them
 * with each doubled backtick read as one; under ANSI_QUOTES a word in double
 * quotes is the text between them with each doubled double quote read as one;
 * any other word, including one made of digits and letters or of bytes above
 * 0x7F, is the name as written. No backslash escape exists in an identifier.
 * Letter case is kept, because the server keeps it and compares names by the
 * rules of each name space. Precision: exact for every token the lexer
 * produces. Terminates: one pass over the token text.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/identifiers.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Identifiers
{
    /**
     * Decodes an identifier token text to the name it denotes.
     */
    public function decode(string $text): string
    {
        $quote = $text[0] ?? '';
        if (($quote === '`' || $quote === '"') && strlen($text) >= 2 && $text[strlen($text) - 1] === $quote) {
            return str_replace($quote . $quote, $quote, substr($text, 1, -1));
        }

        return $text;
    }
}
