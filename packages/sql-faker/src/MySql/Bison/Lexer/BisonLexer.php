<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Lexer;

use SqlFaker\Grammar\GrammarParseException;
use SqlFaker\Grammar\SourceCursor;

/**
 * Turns Bison grammar source into a stream of tokens, one call at a time.
 *
 * The lexer owns no lexeme rule. It skips whitespace, asks the chain which
 * scanner claims the character it is looking at, and hands over the cursor. A
 * scanner that consumed only trivia returns the lexer to that same starting
 * state, so comments cost a loop iteration instead of a recursive call.
 *
 * Lookahead is deliberately not part of this: a parser that needs to peek wraps
 * the lexer in a BisonTokenStream.
 */
final class BisonLexer
{
    /** @readonly */
    private SourceCursor $cursor;

    /** @readonly */
    private BisonScannerChain $scanners;

    /** @readonly */
    private BisonTrivia $trivia;

    /**
     * @param string $source Bison grammar text
     * @param BisonScannerChain|null $scanners Lexeme rules to recognise, defaulting to the Bison set
     * @param BisonTrivia|null $trivia Text between lexemes that carries no token
     */
    public function __construct(
        string $source,
        ?BisonScannerChain $scanners = null,
        ?BisonTrivia $trivia = null,
    ) {
        $this->cursor = new SourceCursor($source);
        $this->scanners = $scanners ?? BisonScannerChain::forBisonGrammar();
        $this->trivia = $trivia ?? new BisonTrivia();
    }

    /**
     * Reads the next token, skipping any whitespace and comments before it.
     *
     * @return BisonToken The next token, or an end-of-file token once the source runs out
     *
     * @throws GrammarParseException When no scanner recognises the character reached
     */
    public function scan(): BisonToken
    {
        $this->trivia->skipFrom($this->cursor);

        $offset = $this->cursor->offset();
        if ($this->cursor->atEnd()) {
            return new BisonToken(BisonLexeme::Eof, '', $offset);
        }

        $character = $this->cursor->current();
        $scanner = $this->scanners->scannerFor($character)
            ?? throw GrammarParseException::unexpectedCharacter($character, $offset);

        return $scanner->scan($this->cursor);
    }

    /**
     * Consumes the rest of the source as raw text.
     *
     * The epilogue of a grammar file is host-language code that has no lexemes
     * of its own, so it is taken whole rather than scanned.
     *
     * @return string Everything the cursor has not passed over yet
     */
    public function consumeRemaining(): string
    {
        return $this->cursor->takeRest();
    }
}
