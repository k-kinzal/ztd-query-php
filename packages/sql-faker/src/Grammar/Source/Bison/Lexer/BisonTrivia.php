<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source\Bison\Lexer;

use SqlFaker\Grammar\Source\GrammarParseException;
use SqlFaker\Grammar\Source\SourceCursor;

/**
 * The text between two lexemes that carries no token of its own.
 *
 * Whitespace and comments may appear anywhere a lexeme may, so treating them as
 * lexemes would mean every scanner returning "nothing this time" and the lexer
 * looping until one of them returned something. Skipping them first keeps every
 * scanner's contract honest: it is asked to read a lexeme, and it always
 * produces one.
 *
 * Inside a semantic action the same comment forms appear but a bare slash is
 * division rather than a mistake, so recognising a comment and requiring one
 * are offered separately.
 */
final class BisonTrivia
{
    /**
     * Skips whitespace and comments until a lexeme starts or the source ends.
     *
     * @param SourceCursor $cursor Cursor to advance
     *
     * @throws GrammarParseException When a slash opens neither comment form
     */
    public function skipFrom(SourceCursor $cursor): void
    {
        while (true) {
            $cursor->skipWhitespace();

            if ($this->skipCommentAt($cursor)) {
                continue;
            }

            if ($cursor->current() === '/') {
                throw GrammarParseException::danglingSlash($cursor->offset());
            }

            return;
        }
    }

    /**
     * Skips one comment when the cursor sits on the start of one.
     *
     * @param SourceCursor $cursor Cursor to advance
     *
     * @return bool True when a comment was consumed, false when the cursor did not move
     */
    public function skipCommentAt(SourceCursor $cursor): bool
    {
        if ($cursor->current() !== '/') {
            return false;
        }

        return match ($cursor->peek()) {
            '/' => $this->skipToEndOfLine($cursor),
            '*' => $this->skipToCommentClose($cursor),
            default => false,
        };
    }

    /**
     * Consumes a line comment, stopping before the newline that ends it.
     *
     * The newline is left behind because it is whitespace, and whitespace is
     * skipped by the caller that owns the loop.
     *
     * @param SourceCursor $cursor Cursor positioned on the opening slash
     *
     * @return bool Always true
     */
    public function skipToEndOfLine(SourceCursor $cursor): bool
    {
        $cursor->advance(2);
        $cursor->takeWhile(static fn (string $character): bool => $character !== "\n");

        return true;
    }

    /**
     * Consumes a block comment together with its terminator.
     *
     * A comment left open by a truncated grammar ends at the end of the file.
     *
     * @param SourceCursor $cursor Cursor positioned on the opening slash
     *
     * @return bool Always true
     */
    public function skipToCommentClose(SourceCursor $cursor): bool
    {
        $cursor->advance(2);
        $cursor->takeUntil('*/');

        return true;
    }
}
