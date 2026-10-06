<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules;

/**
 * Decodes the spelling of a SQLite identifier token.
 *
 * Rule: SQLITE-IDENTIFIER-DECODE-001. A word in double quotes, backticks or
 * single quotes is the text between them with each doubled quote read as one;
 * a word in square brackets is the text between them; any other word is the
 * name as written. Letter case is kept, because SQLite keeps it and compares
 * names without regard to ASCII case. Source: https://sqlite.org/lang_keywords.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class Identifiers
{
    /**
     * Decodes an identifier token text to the name it denotes.
     */
    public function decode(string $text): string
    {
        $quote = $text[0] ?? '';
        if ($quote === '[') {
            return substr($text, 1, -1);
        }
        if ($quote === '"' || $quote === '`' || $quote === "'") {
            return str_replace($quote . $quote, $quote, substr($text, 1, -1));
        }

        return $text;
    }
}
