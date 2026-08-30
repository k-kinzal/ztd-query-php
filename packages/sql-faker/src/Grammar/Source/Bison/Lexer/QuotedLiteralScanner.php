<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source\Bison\Lexer;

use Override;
use SqlFaker\Grammar\Source\SourceCursor;

/**
 * Consumes a quoted literal.
 *
 * Bison spells a token's own text in single quotes and its alias in double
 * quotes, so the quote character decides which lexeme was written. Both are
 * read the same way, escapes included; only the resulting lexeme differs.
 */
final class QuotedLiteralScanner implements BisonScanner
{
    /**
     * Reports whether a quoted literal can start here.
     *
     * @param string $character A single character; never the empty string
     *
     * @return bool True for either quote character
     */
    #[Override]
    public function handles(string $character): bool
    {
        return $character === '\'' || $character === '"';
    }

    /**
     * Consumes the literal including both quotes.
     *
     * @param SourceCursor $cursor Cursor positioned on the opening quote
     *
     * @return BisonToken The unescaped text between the quotes
     */
    #[Override]
    public function scan(SourceCursor $cursor): BisonToken
    {
        $offset = $cursor->offset();
        $quote = $cursor->current();
        $lexeme = $quote === '\'' ? BisonLexeme::CharLiteral : BisonLexeme::StringLiteral;

        return new BisonToken($lexeme, $cursor->takeQuoted($quote), $offset);
    }
}
