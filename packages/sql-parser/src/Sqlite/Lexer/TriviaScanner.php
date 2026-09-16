<?php

declare(strict_types=1);

namespace SqlParser\Sqlite\Lexer;

/**
 * Skips the whitespace and comments between SQLite tokens.
 *
 * A block comment that never closes runs to the end of the text, as it does
 * in SQLite, and a byte order mark at the start is whitespace too.
 *
 * @visibility root
 */
final class TriviaScanner
{
    /**
     * Skips every whitespace and comment at the cursor.
     *
     * @param Scan $scan The tokenization in progress
     */
    public function skip(Scan $scan): void
    {
        $cursor = $scan->cursor;
        while (!$cursor->eof()) {
            if ($cursor->match('[ \t\n\v\f\r]+') !== null || $cursor->match('--[^\n]*') !== null) {
                continue;
            }
            if ($cursor->offset() === 0 && $cursor->startsWith("\xEF\xBB\xBF")) {
                $cursor->take(3);
                continue;
            }
            if ($cursor->startsWith('/*') && $cursor->peek(2) !== '') {
                $cursor->take(2);
                $cursor->skipPastOrEnd('*/');
                continue;
            }

            return;
        }
    }
}
