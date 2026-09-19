<?php

declare(strict_types=1);

namespace SqlParser\PostgreSql\Lexer;

use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;

/**
 * Reads PostgreSQL's quoted tokens: strings in their six spellings and quoted identifiers.
 *
 * Two quoted strings separated by whitespace that holds a newline are one
 * string, as the standard requires. A string with escapes, `E'...'`, lets a
 * backslash escape the next character; the others double a quote to write
 * it. A bit string, a hexadecimal string, a Unicode string and a dollar-quoted
 * string each have a spelling of their own.
 *
 * @visibility root
 */
final class QuotedScanner
{
    /**
     * Whitespace with a newline in it, followed by the quote that continues a string.
     */
    public const CONTINUATION = '(?:[ \t\f\v]|--[^\n\r]*)*[\n\r](?:[ \t\n\r\f\v]+|--[^\n\r]*[\n\r])*\'';

    /**
     * Reads the quoted token at the cursor, if one starts there.
     *
     * @param Scan $scan The tokenization in progress
     *
     * @return Lexeme|null The lexeme, or null when no quoted token starts here
     *
     * @throws LexicalException When the token never closes or a quoted identifier is empty
     */
    public function scan(Scan $scan): ?Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $character = $cursor->peek();
        if ($character === "'") {
            return $this->string($scan, 'SCONST', $start, false);
        }
        if ($character === '"') {
            return $this->identifier($scan, 'IDENT', $start);
        }
        $prefix = $cursor->match("[eE]'|[bB]'|[xX]'|[uU]&'|[uU]&\"");
        if ($prefix !== null) {
            $cursor->seek($cursor->offset() - 1);
            $letter = strtoupper($prefix[0]);
            if (str_ends_with($prefix, '"')) {
                return $this->identifier($scan, 'UIDENT', $start);
            }

            return match ($letter) {
                'E' => $this->string($scan, 'SCONST', $start, true),
                'B' => $this->string($scan, 'BCONST', $start, false, false),
                'X' => $this->string($scan, 'XCONST', $start, false, false),
                default => $this->string($scan, 'USCONST', $start, false),
            };
        }
        if ($cursor->match("[nN]'") !== null) {
            $cursor->seek($start + 1);

            return $scan->lexeme($scan->keywords->lookup('nchar') ?? 'IDENT', $start);
        }

        return $this->dollarQuoted($scan);
    }

    /**
     * Reads a string whose opening quote is at the cursor, with any continuations.
     *
     * @param Scan $scan The tokenization in progress, positioned on the opening quote
     * @param string $name Terminal the string stands for
     * @param int $start Offset the lexeme began at, before any prefix
     * @param bool $escapes Whether a backslash escapes the next character
     * @param bool $doubling Whether a doubled quote stands for a quote inside the string
     *
     * @return Lexeme The lexeme
     *
     * @throws LexicalException When the string never closes
     */
    public function string(Scan $scan, string $name, int $start, bool $escapes, bool $doubling = true): Lexeme
    {
        $cursor = $scan->cursor;
        while (true) {
            $cursor->take(1);
            $this->body($scan, $start, $escapes, $doubling);
            if ($cursor->match(self::CONTINUATION) === null) {
                return $scan->lexeme($name, $start);
            }
            $cursor->seek($cursor->offset() - 1);
        }
    }

    /**
     * Consumes the body of a string up to and including its closing quote.
     *
     * @param Scan $scan The tokenization in progress, positioned after the opening quote
     * @param int $start Offset the lexeme began at, for the error
     * @param bool $escapes Whether a backslash escapes the next character
     * @param bool $doubling Whether a doubled quote stands for a quote inside the string
     *
     * @throws LexicalException When the string never closes
     */
    public function body(Scan $scan, int $start, bool $escapes, bool $doubling): void
    {
        $cursor = $scan->cursor;
        while (!$cursor->eof()) {
            $character = $cursor->take(1);
            if ($character === '\\' && $escapes) {
                $cursor->take(1);
            } elseif ($character === "'") {
                if ($doubling && $cursor->peek() === "'") {
                    $cursor->take(1);
                    continue;
                }

                return;
            }
        }

        throw LexicalException::unterminated('quoted string', $cursor->source, $start);
    }

    /**
     * Reads a double-quoted identifier.
     *
     * @param Scan $scan The tokenization in progress, positioned on the opening quote
     * @param string $name Terminal the identifier stands for
     * @param int $start Offset the lexeme began at, before any prefix
     *
     * @return Lexeme The lexeme
     *
     * @throws LexicalException When the identifier never closes or is empty
     */
    public function identifier(Scan $scan, string $name, int $start): Lexeme
    {
        $cursor = $scan->cursor;
        $quoted = $cursor->takeQuoted('"');
        if ($quoted === null) {
            throw LexicalException::unterminated('quoted identifier', $cursor->source, $start);
        }
        if ($quoted === '""') {
            throw LexicalException::unexpectedCharacter($cursor->source, $start);
        }

        return $scan->lexeme($name, $start);
    }

    /**
     * Reads a dollar-quoted string, if one starts at the cursor.
     *
     * @param Scan $scan The tokenization in progress
     *
     * @return Lexeme|null The lexeme, or null when no dollar-quoted string starts here
     *
     * @throws LexicalException When the string never closes
     */
    public function dollarQuoted(Scan $scan): ?Lexeme
    {
        $cursor = $scan->cursor;
        $start = $cursor->offset();
        $tag = $cursor->match('\$(?:[A-Za-z\x80-\xFF_][A-Za-z\x80-\xFF_0-9]*)?\$');
        if ($tag === null) {
            return null;
        }
        $end = strpos($cursor->source, $tag, $cursor->offset());
        if ($end === false) {
            throw LexicalException::unterminated('dollar-quoted string', $cursor->source, $start);
        }
        $cursor->seek($end + strlen($tag));

        return $scan->lexeme('SCONST', $start);
    }
}
