<?php

declare(strict_types=1);

namespace SqlParser\PostgreSql\Lexer;

use SqlParser\Lexer\LexicalException;

/**
 * Skips the whitespace and comments between PostgreSQL tokens.
 *
 * Block comments nest, so the depth is counted rather than the first
 * closing marker taken as the end.
 *
 * @visibility root
 */
final class TriviaScanner
{
    /**
     * Skips every whitespace and comment at the cursor.
     *
     * @param Scan $scan The tokenization in progress
     *
     * @throws LexicalException When a block comment never closes
     */
    public function skip(Scan $scan): void
    {
        $cursor = $scan->cursor;
        while (!$cursor->eof()) {
            if ($cursor->match('[ \t\n\r\f\v]+') !== null || $cursor->match('--[^\n\r]*') !== null) {
                continue;
            }
            if (!$cursor->startsWith('/*')) {
                return;
            }
            $this->blockComment($scan);
        }
    }

    /**
     * Skips a block comment and the comments nested in it.
     *
     * @param Scan $scan The tokenization in progress, positioned on the comment
     *
     * @throws LexicalException When the comment never closes
     */
    public function blockComment(Scan $scan): void
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $depth = 0;
        do {
            if ($cursor->eof()) {
                throw LexicalException::unterminated('comment', $cursor->source, $start);
            }
            if ($cursor->startsWith('/*')) {
                $depth++;
                $cursor->take(2);
            } elseif ($cursor->startsWith('*/')) {
                $depth--;
                $cursor->take(2);
            } else {
                $cursor->take(1);
            }
        } while ($depth > 0);
    }
}
