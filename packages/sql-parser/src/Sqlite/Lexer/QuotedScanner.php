<?php

declare(strict_types=1);

namespace SqlParser\Sqlite\Lexer;

use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;

/**
 * Reads SQLite's quoted tokens: strings, quoted identifiers and blob literals.
 *
 * Single quotes delimit strings; double quotes, backticks and brackets
 * delimit identifiers. A quote inside doubles itself, except in brackets.
 *
 * @visibility root
 */
final class QuotedScanner
{
    /**
     * Reads the quoted token at the cursor, if one starts there.
     *
     * @param Scan $scan The tokenization in progress
     *
     * @return Lexeme|null The lexeme, or null when no quoted token starts here
     *
     * @throws LexicalException When the token never closes or a blob is malformed
     */
    public function scan(Scan $scan): ?Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $character = $cursor->peek();
        if ($character === "'" || $character === '"' || $character === '`') {
            if ($cursor->takeQuoted($character) === null) {
                throw LexicalException::unterminated($character === "'" ? 'string' : 'quoted identifier', $cursor->source, $start);
            }

            return $scan->lexeme($character === "'" ? 'STRING' : 'ID', $start);
        }
        if ($character === '[') {
            if (!$cursor->skipPastOrEnd(']')) {
                throw LexicalException::unterminated('bracketed identifier', $cursor->source, $start);
            }

            return $scan->lexeme('ID', $start);
        }
        if (($character === 'x' || $character === 'X') && $cursor->peek(1) === "'") {
            if ($cursor->match("[xX]'(?:[0-9A-Fa-f]{2})*'") === null) {
                throw LexicalException::unexpectedCharacter($cursor->source, $start);
            }

            return $scan->lexeme('BLOB', $start);
        }

        return null;
    }
}
