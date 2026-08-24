<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Lexer;

use SqlFaker\Grammar\SourceCursor;

/**
 * One state of the Bison lexer, selected by the character it starts on.
 *
 * The lexer holds no rule of its own: it asks the chain which scanner claims
 * the character under the cursor and hands the cursor over. Whitespace and
 * comments are already gone by the time a scanner is consulted, so a scanner
 * that claims a character always produces a token from it.
 */
interface BisonScanner
{
    /**
     * Reports whether this scanner recognises a lexeme starting at the character.
     *
     * @param string $character A single character; never the empty string
     *
     * @return bool True when this scanner should consume the lexeme
     */
    public function handles(string $character): bool;

    /**
     * Consumes one lexeme, leaving the cursor after it.
     *
     * @param SourceCursor $cursor Cursor positioned on the character this scanner claimed
     *
     * @return BisonToken The recognised token
     */
    public function scan(SourceCursor $cursor): BisonToken;
}
