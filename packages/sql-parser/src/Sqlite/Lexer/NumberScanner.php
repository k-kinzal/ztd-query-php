<?php

declare(strict_types=1);

namespace SqlParser\Sqlite\Lexer;

use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;

/**
 * Reads SQLite's numeric literals.
 *
 * A number with underscores between its digits is `QNUMBER`; otherwise a
 * hexadecimal or plain integer is `INTEGER` and anything with a fraction or
 * exponent is `FLOAT`. An identifier character right after a number makes
 * the whole run illegal.
 *
 * @visibility root
 */
final class NumberScanner
{
    /**
     * Reads the number at the cursor, if one starts there.
     *
     * @param Scan $scan The tokenization in progress
     *
     * @return Lexeme|null The lexeme, or null when no number starts here
     *
     * @throws LexicalException When an identifier character follows the number
     */
    public function scan(Scan $scan): ?Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $text = $cursor->match('0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*');
        if ($text !== null) {
            $name = str_contains($text, '_') ? 'QNUMBER' : 'INTEGER';
        } else {
            $digits = '[0-9](?:_?[0-9])*';
            $text = $cursor->match("(?:{$digits}(?:\\.(?:{$digits})?)?|\\.{$digits})(?:[eE][+-]?{$digits})?");
            if ($text === null) {
                return null;
            }
            $name = str_contains($text, '_') ? 'QNUMBER' : (preg_match('/[.eE]/', $text) === 1 ? 'FLOAT' : 'INTEGER');
        }
        if (Scan::isIdentifierByte($cursor->peek())) {
            throw LexicalException::unexpectedCharacter($cursor->source, $start);
        }

        return $scan->lexeme($name, $start);
    }
}
