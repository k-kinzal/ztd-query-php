<?php

declare(strict_types=1);

namespace BisonParser\Scanner;

use BisonParser\SyntaxException;

/**
 * Reads host code as Bison's scanner does: braced code, a prologue, or one unit of either.
 *
 * Braces nest, the digraphs `<%` and `%>` count as braces, and braces
 * inside comments, strings and character literals do not count. A
 * backslash-newline, which C splices away, may fall between the two
 * characters of `/*`, `* /`, `//`, `<%`, `%>` and `<<`, and inside a
 * string or character literal, exactly as `scan-gram.l` allows.
 *
 * @visibility root
 */
final class CodeReader
{
    /**
     * Backslash-newlines that C splices away, as a regular expression fragment.
     */
    public const SPLICE = '(?:\\\\[ \f\t\v]*\r?\n)*';

    /**
     * Reads the braced code the cursor is at and returns the text between the braces.
     *
     * @param Cursor $cursor Positioned at the opening brace
     *
     * @return string The code without its braces
     *
     * @throws SyntaxException When the file ends before the closing brace
     */
    public function braced(Cursor $cursor): string
    {
        $start = $cursor->location();
        $cursor->take(1);
        $code = '';
        $nesting = 1;
        while (!$cursor->eof()) {
            $unit = $this->unit($cursor);
            if ($this->opens($unit)) {
                $nesting++;
            } elseif ($this->closes($unit)) {
                $nesting--;
                if ($nesting === 0 && $unit === '}') {
                    return $code;
                }
            }
            $code .= $unit;
        }
        throw SyntaxException::unterminated('braced code', '}', $start);
    }

    /**
     * Reads the prologue the cursor is at and returns the text between `%{` and `%}`.
     *
     * @param Cursor $cursor Positioned at `%{`
     *
     * @return string The code without its delimiters
     *
     * @throws SyntaxException When the file ends before `%}`
     */
    public function prologue(Cursor $cursor): string
    {
        $start = $cursor->location();
        $cursor->take(2);
        $code = '';
        while (!$cursor->eof()) {
            if ($cursor->startsWith('%}')) {
                $cursor->take(2);

                return $code;
            }
            $code .= $this->unit($cursor);
        }
        throw SyntaxException::unterminated('prologue', '%}', $start);
    }

    /**
     * Reads one unit of host code: a comment, a string, a character literal, a digraph, or one byte.
     *
     * @param Cursor $cursor Positioned at the unit
     *
     * @return string The unit as written
     *
     * @throws SyntaxException When a comment, string or character literal is not closed
     */
    public function unit(Cursor $cursor): string
    {
        $start = $cursor->location();
        $opening = $cursor->match('/' . self::SPLICE . '\*');
        if ($opening !== null) {
            $body = $cursor->match('(?s).*?\*' . self::SPLICE . '/');
            if ($body === null) {
                throw SyntaxException::unterminated('comment', '*/', $start);
            }

            return $opening . $body;
        }
        $line = $cursor->match('/' . self::SPLICE . '/(?:\\\\[ \f\t\v]*\r?\n|[^\n])*');
        if ($line !== null) {
            return $line;
        }
        $byte = $cursor->peek();
        if ($byte === '"' || $byte === "'") {
            $quote = preg_quote($byte, '~');
            $literal = $cursor->match($quote . '(?:\\\\' . self::SPLICE . '[^\n\[\]]|\\\\[ \f\t\v]*\r?\n|[^\\\\' . $quote . '\n]|\\\\)*' . $quote);
            if ($literal === null) {
                throw SyntaxException::unterminated($byte === '"' ? 'string' : 'character literal', $byte, $start);
            }

            return $literal;
        }

        return $cursor->match('<' . self::SPLICE . '[%<]|%' . self::SPLICE . '>') ?? $cursor->take(1);
    }

    /**
     * Reports whether a unit opens a brace: `{` or the digraph `<%`, possibly spliced.
     *
     * @param string $unit The unit
     *
     * @return bool True for an opening brace
     */
    public function opens(string $unit): bool
    {
        return $unit === '{' || preg_match('~^<' . self::SPLICE . '%$~', $unit) === 1;
    }

    /**
     * Reports whether a unit closes a brace: `}` or the digraph `%>`, possibly spliced.
     *
     * @param string $unit The unit
     *
     * @return bool True for a closing brace
     */
    public function closes(string $unit): bool
    {
        return $unit === '}' || preg_match('~^%' . self::SPLICE . '>$~', $unit) === 1;
    }
}
