<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source\Bison\Lexer;

use Override;
use SqlFaker\Grammar\Source\GrammarParseException;
use SqlFaker\Grammar\Source\SourceCursor;

/**
 * Consumes an angle-bracketed semantic type tag such as `<num>`.
 *
 * Unlike a prologue or an action, a tag that is never closed is not treated as
 * running to the end of the file: the rest of the grammar would be swallowed
 * into a type name, and every declaration after it would silently disappear.
 * The truncation is reported instead.
 */
final class TypeTagScanner implements BisonScanner
{
    /**
     * Reports whether a type tag can start here.
     *
     * @param string $character A single character; never the empty string
     *
     * @return bool True for an opening angle bracket
     */
    #[Override]
    public function handles(string $character): bool
    {
        return $character === '<';
    }

    /**
     * Consumes the tag and its closing bracket.
     *
     * @param SourceCursor $cursor Cursor positioned on the opening bracket
     *
     * @return BisonToken The tag name, trimmed of surrounding whitespace
     *
     * @throws GrammarParseException When the tag is never closed
     */
    #[Override]
    public function scan(SourceCursor $cursor): BisonToken
    {
        $offset = $cursor->offset();
        $cursor->advance();
        $tag = $cursor->takeWhile(static fn (string $character): bool => $character !== '>');

        if ($cursor->atEnd()) {
            throw GrammarParseException::unterminatedTypeTag($offset);
        }

        $cursor->advance();

        return new BisonToken(BisonLexeme::TypeTag, trim($tag), $offset);
    }
}
