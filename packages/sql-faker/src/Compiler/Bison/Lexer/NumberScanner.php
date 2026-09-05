<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Bison\Lexer;

use Override;
use SqlFaker\Grammar\SourceCursor;

/**
 * Consumes a decimal number.
 *
 * Numbers appear in a grammar as explicit token codes and as the counts of
 * `%expect`, so the value is carried as an integer rather than as the digits
 * that spelled it.
 *
 * @visibility root
 */
final class NumberScanner implements BisonScanner
{
    /**
     * Reports whether a number can start here.
     *
     * @param string $character A single character; never the empty string
     *
     * @return bool True for a decimal digit
     */
    #[Override]
    public function handles(string $character): bool
    {
        return ctype_digit($character);
    }

    /**
     * Consumes the run of digits.
     *
     * @param SourceCursor $cursor Cursor positioned on the first digit
     *
     * @return BisonToken The number as an integer value
     */
    #[Override]
    public function scan(SourceCursor $cursor): BisonToken
    {
        $offset = $cursor->offset();
        $digits = $cursor->takeWhile(static fn (string $character): bool => ctype_digit($character));

        return new BisonToken(BisonLexeme::Number, (int) $digits, $offset);
    }
}
