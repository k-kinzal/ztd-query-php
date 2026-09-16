<?php

declare(strict_types=1);

namespace SqlParser\MySql\Lexer;

use SqlParser\Lexer\LexicalException;

/**
 * Skips the whitespace and comments between MySQL tokens.
 *
 * A `/*!` comment is versioned: when the release it names is not newer than
 * the release being read for, its body is SQL and only the markers are
 * skipped, with the closing marker recognised later at a token boundary.
 * Any other block comment, a `#` comment, and a `--` comment followed by a
 * space are skipped whole.
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
            if ($cursor->match('\s+') !== null) {
                continue;
            }
            if ($cursor->peek() === '#' || ($cursor->startsWith('--') && ($cursor->peek(2) === '' || ctype_space($cursor->peek(2)) || ctype_cntrl($cursor->peek(2))))) {
                $cursor->match('[^\n]*');
                continue;
            }
            if ($cursor->startsWith('*/') && $scan->inVersionComment) {
                $cursor->take(2);
                $scan->inVersionComment = false;
                continue;
            }
            if (!$cursor->startsWith('/*')) {
                return;
            }
            $this->blockComment($scan);
        }
    }

    /**
     * Skips a block comment, or only its opening marker when its body is SQL.
     *
     * @param Scan $scan The tokenization in progress, positioned on the comment
     *
     * @throws LexicalException When the comment never closes
     */
    public function blockComment(Scan $scan): void
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        if ($cursor->peek(2) === '!' && !$scan->inVersionComment) {
            $cursor->take(3);
            $version = $cursor->match('[0-9]{5,6}');
            if ($version === null || (int) $version <= $scan->version->id()) {
                $scan->inVersionComment = true;

                return;
            }
        } else {
            $cursor->take(2);
        }
        $depth = 1;
        while ($depth > 0) {
            if ($cursor->eof()) {
                throw LexicalException::unterminated('comment', $cursor->source, $start);
            }
            if ($cursor->startsWith('*/')) {
                $depth--;
            } elseif ($cursor->startsWith('/*') && $depth === 1 && $scan->inVersionComment === false && $cursor->peek(2) === '!') {
                $depth++;
            }
            $cursor->take($depth === 0 || $cursor->startsWith('/*') ? 2 : 1);
        }
    }
}
