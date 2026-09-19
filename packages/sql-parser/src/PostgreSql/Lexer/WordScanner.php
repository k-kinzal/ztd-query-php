<?php

declare(strict_types=1);

namespace SqlParser\PostgreSql\Lexer;

use SqlParser\Lexer\Lexeme;

/**
 * Reads PostgreSQL's unquoted identifiers and keywords.
 *
 * @visibility root
 */
final class WordScanner
{
    /**
     * Reads the word at the cursor, if one starts there.
     *
     * @param Scan $scan The tokenization in progress
     *
     * @return Lexeme|null The lexeme, or null when no word starts here
     */
    public function scan(Scan $scan): ?Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $word = $cursor->match('[A-Za-z\x80-\xFF_][A-Za-z\x80-\xFF_0-9$]*');
        if ($word === null) {
            return null;
        }

        return $scan->lexeme($scan->keywords->lookup($word) ?? 'IDENT', $start);
    }
}
