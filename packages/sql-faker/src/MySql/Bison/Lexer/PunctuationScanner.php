<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Lexer;

use Override;
use SqlFaker\Grammar\SourceCursor;

/**
 * Consumes a single punctuation character.
 *
 * Three of these carry grammatical weight — a colon opens a rule's
 * alternatives, a pipe separates them and a semicolon closes the rule — so each
 * gets a lexeme of its own. The rest appear only inside directive arguments,
 * where the parser needs the character but not a distinct lexeme for it, and
 * they are reported as the character they are.
 */
final class PunctuationScanner implements BisonScanner
{
    /**
     * Reports whether the character is punctuation the grammar language uses.
     *
     * @param string $character A single character; never the empty string
     *
     * @return bool True for a punctuation character
     */
    #[Override]
    public function handles(string $character): bool
    {
        return str_contains(':;|=,)([]', $character);
    }

    /**
     * Consumes the character.
     *
     * @param SourceCursor $cursor Cursor positioned on the punctuation
     *
     * @return BisonToken The punctuation as its own lexeme, or as a character literal
     */
    #[Override]
    public function scan(SourceCursor $cursor): BisonToken
    {
        $offset = $cursor->offset();
        $character = $cursor->current();
        $cursor->advance();

        $lexeme = match ($character) {
            ':' => BisonLexeme::Colon,
            ';' => BisonLexeme::Semicolon,
            '|' => BisonLexeme::Pipe,
            default => BisonLexeme::CharLiteral,
        };

        return new BisonToken($lexeme, $character, $offset);
    }
}
