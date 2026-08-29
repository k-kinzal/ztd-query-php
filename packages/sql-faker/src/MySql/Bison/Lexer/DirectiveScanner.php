<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Lexer;

use Override;
use SqlFaker\Grammar\Source\GrammarParseException;
use SqlFaker\Grammar\Source\SourceCursor;

/**
 * Consumes the three lexemes a percent sign can open.
 *
 * `%%` separates the grammar's sections, `%{ ... %}` wraps a prologue of host
 * code, and anything else is a named directive such as `%token`. They share a
 * starting character but nothing else, so the second character decides which
 * of the three is being read.
 */
final class DirectiveScanner implements BisonScanner
{
    /**
     * Reports whether a directive can start here.
     *
     * @param string $character A single character; never the empty string
     *
     * @return bool True for a percent sign
     */
    #[Override]
    public function handles(string $character): bool
    {
        return $character === '%';
    }

    /**
     * Consumes the section separator, prologue or directive name.
     *
     * @param SourceCursor $cursor Cursor positioned on the percent sign
     *
     * @return BisonToken The recognised token
     *
     * @throws GrammarParseException When the percent sign is not followed by a directive name
     */
    #[Override]
    public function scan(SourceCursor $cursor): BisonToken
    {
        return match ($cursor->peek()) {
            '%' => $this->scanSectionSeparator($cursor),
            '{' => $this->scanPrologue($cursor),
            default => $this->scanDirectiveName($cursor),
        };
    }

    /**
     * Consumes the `%%` that separates two sections of the grammar.
     *
     * @param SourceCursor $cursor Cursor positioned on the first percent sign
     *
     * @return BisonToken The section separator
     */
    public function scanSectionSeparator(SourceCursor $cursor): BisonToken
    {
        $offset = $cursor->offset();
        $cursor->advance(2);

        return new BisonToken(BisonLexeme::PercentPercent, '%%', $offset);
    }

    /**
     * Consumes a `%{ ... %}` prologue of host-language code.
     *
     * An unterminated prologue takes the rest of the file as its body, which
     * matches how Bison itself reads a grammar that was cut short.
     *
     * @param SourceCursor $cursor Cursor positioned on the percent sign
     *
     * @return BisonToken The prologue and its contents
     */
    public function scanPrologue(SourceCursor $cursor): BisonToken
    {
        $offset = $cursor->offset();
        $cursor->advance(2);

        return new BisonToken(BisonLexeme::Prologue, $cursor->takeUntil('%}'), $offset);
    }

    /**
     * Consumes a named directive such as `%token` or `%parse-param`.
     *
     * @param SourceCursor $cursor Cursor positioned on the percent sign
     *
     * @return BisonToken The directive including its leading percent sign
     *
     * @throws GrammarParseException When no name follows the percent sign
     */
    public function scanDirectiveName(SourceCursor $cursor): BisonToken
    {
        $offset = $cursor->offset();
        $cursor->advance();
        $name = $cursor->takeWhile(
            static fn (string $character): bool => preg_match('/[A-Za-z0-9_.-]/', $character) === 1,
        );

        if ($name === '') {
            throw GrammarParseException::namelessDirective($offset);
        }

        return new BisonToken(BisonLexeme::Directive, '%' . $name, $offset);
    }
}
