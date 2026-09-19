<?php

declare(strict_types=1);

namespace SqlParser\MySql\Lexer;

use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;

/**
 * Reads MySQL's quoted tokens: strings, quoted identifiers and prefixed literals.
 *
 * A single-quoted run is a string, and so is a double-quoted one unless
 * `ANSI_QUOTES` makes it an identifier. Backticks always quote identifiers.
 * A quote right after `N`, `X` or `B` opens a national string, a hexadecimal
 * literal or a bit literal.
 *
 * @visibility root
 */
final class QuotedScanner
{
    /**
     * Reads the quoted token at the cursor, if one starts there.
     *
     * @param Scan $scan The tokenization in progress
     *
     * @return Lexeme|null The lexeme, or null when no quoted token starts here
     *
     * @throws LexicalException When the token never closes or a hexadecimal literal has an odd length
     */
    public function scan(Scan $scan): ?Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $character = $cursor->peek();
        if ($character === "'" || ($character === '"' && !$scan->mode->ansiQuotes)) {
            return $this->string($scan, 'TEXT_STRING', $character);
        }
        if ($character === '`' || $character === '"') {
            return $this->quotedIdentifier($scan, $character);
        }
        if ($cursor->peek(1) !== "'") {
            return null;
        }
        $prefix = strtoupper($character);
        if ($prefix === 'N') {
            $cursor->take(1);

            return $this->string($scan, 'NCHAR_STRING', "'", $start);
        }
        if ($prefix === 'X' || $prefix === 'B') {
            $digits = $cursor->match($prefix === 'X' ? "[xX]'[0-9A-Fa-f]*'" : "[bB]'[01]*'");
            if ($digits === null || ($prefix === 'X' && strlen($digits) % 2 === 0)) {
                throw LexicalException::unterminated($prefix === 'X' ? 'hexadecimal literal' : 'bit literal', $cursor->source, $start);
            }

            return $scan->lexeme($prefix === 'X' ? 'HEX_NUM' : 'BIN_NUM', $start);
        }

        return null;
    }

    /**
     * Reads a string literal delimited by a quote.
     *
     * @param Scan $scan The tokenization in progress, positioned on the quote
     * @param string $name Terminal the string stands for
     * @param string $quote The quote character
     * @param int|null $start Offset the lexeme began at, when a prefix came first
     *
     * @return Lexeme The lexeme
     *
     * @throws LexicalException When the string never closes
     */
    public function string(Scan $scan, string $name, string $quote, ?int $start = null): Lexeme
    {
        $cursor = $scan->cursor;
        $start ??= $cursor->offset();
        if ($cursor->takeQuoted($quote, !$scan->mode->noBackslashEscapes) === null) {
            throw LexicalException::unterminated('string', $cursor->source, $start);
        }

        return $scan->lexeme($name, $start);
    }

    /**
     * Reads an identifier delimited by backticks or, under `ANSI_QUOTES`, double quotes.
     *
     * @param Scan $scan The tokenization in progress, positioned on the quote
     * @param string $quote The quote character
     *
     * @return Lexeme The lexeme
     *
     * @throws LexicalException When the identifier never closes
     */
    public function quotedIdentifier(Scan $scan, string $quote): Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        if ($cursor->takeQuoted($quote) === null) {
            throw LexicalException::unterminated('quoted identifier', $cursor->source, $start);
        }

        return $scan->lexeme('IDENT_QUOTED', $start);
    }
}
