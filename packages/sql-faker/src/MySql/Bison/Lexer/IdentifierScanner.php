<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Lexer;

use Override;
use SqlFaker\Grammar\SourceCursor;

/**
 * Consumes a symbol name.
 *
 * Rule names and token names share one spelling in Bison: which of the two a
 * name refers to is decided by the parser from where it appears, not by the
 * lexer. Digits and dots may follow the first character but may not open the
 * name, which is what keeps a number from being read as an identifier.
 *
 * @visibility root
 */
final class IdentifierScanner implements BisonScanner
{
    /**
     * Reports whether a symbol name can start here.
     *
     * @param string $character A single character; never the empty string
     *
     * @return bool True for a letter or an underscore
     */
    #[Override]
    public function handles(string $character): bool
    {
        return preg_match('/[A-Za-z_]/', $character) === 1;
    }

    /**
     * Consumes the name.
     *
     * @param SourceCursor $cursor Cursor positioned on the first character
     *
     * @return BisonToken The symbol name
     */
    #[Override]
    public function scan(SourceCursor $cursor): BisonToken
    {
        $offset = $cursor->offset();
        $name = $cursor->takeWhile(
            static fn (string $character): bool => preg_match('/[A-Za-z0-9_.]/', $character) === 1,
        );

        return new BisonToken(BisonLexeme::Identifier, $name, $offset);
    }
}
