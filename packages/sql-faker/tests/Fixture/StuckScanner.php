<?php

declare(strict_types=1);

namespace Tests\Fixture\SqlFaker;

use Override;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonScanner;
use SqlFaker\MySql\Bison\Lexer\BisonToken;

/**
 * A scanner that answers a token without consuming the character it read.
 *
 * Reading a token is what moves a lexer through its source, so a scanner like
 * this one leaves the lexer answering the same token forever. It exists so a
 * test can say what the lexer does about that.
 */
final class StuckScanner implements BisonScanner
{
    /**
     * {@inheritDoc}
     *
     * @param string $character Character the lexer reached
     *
     * @return bool Always true, because this stands in for whichever scanner was chosen
     */
    #[Override]
    public function handles(string $character): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @param SourceCursor $cursor Cursor over the source, left exactly where it was
     *
     * @return BisonToken A token over the character the cursor still sits on
     */
    #[Override]
    public function scan(SourceCursor $cursor): BisonToken
    {
        return new BisonToken(BisonLexeme::CharLiteral, $cursor->current(), $cursor->offset());
    }
}
