<?php

declare(strict_types=1);

namespace BisonParser\Scanner;

use BisonParser\SyntaxException;

/**
 * Reads the host code Bison keeps as one token: braced code, predicates, and the prologue.
 *
 * Braces are counted, and so are the digraphs `<%` and `%>`, while the
 * contents of string literals, character literals and comments are passed
 * over, as `scan-gram.l` passes over them.
 *
 * @visibility root
 */
final class CodeReader
{
    /**
     * Reads braced code that opens at the cursor, answering the text between the braces.
     *
     * @param Cursor $cursor Cursor positioned on the opening brace
     *
     * @return string The code without its braces
     *
     * @throws SyntaxException When the code never closes
     */
    public function braced(Cursor $cursor): string
    {
        $start = $cursor->location();
        $cursor->take(1);
        $code = '';
        $nesting = 0;
        while (!$cursor->eof()) {
            $unit = $this->unit($cursor);
            if ($unit === '{' || $unit === '<%') {
                $nesting++;
            } elseif ($unit === '}' || $unit === '%>') {
                if ($nesting === 0) {
                    return $code;
                }
                $nesting--;
            }
            $code .= $unit;
        }

        throw SyntaxException::unterminated('braced code', '}', $start);
    }

    /**
     * Reads the prologue that opens at the cursor, answering the text between `%{` and `%}`.
     *
     * @param Cursor $cursor Cursor positioned on `%{`
     *
     * @return string The code without its markers
     *
     * @throws SyntaxException When the prologue never closes
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
     * Reads one unit of host code: a literal, a comment, a digraph, or a byte.
     *
     * A literal or comment is answered whole, so a brace inside one is never
     * mistaken for a brace of the grammar.
     *
     * @param Cursor $cursor Cursor positioned on the unit
     *
     * @return string The consumed text
     *
     * @throws SyntaxException When a literal or comment never closes
     */
    public function unit(Cursor $cursor): string
    {
        $start = $cursor->location();
        if ($cursor->startsWith('/*')) {
            $body = $cursor->takeUntil('*/');
            if ($body === null) {
                throw SyntaxException::unterminated('comment', '*/', $start);
            }

            return $body . '*/';
        }
        if ($cursor->startsWith('//')) {
            return $cursor->match('//[^\n]*') ?? '';
        }
        $byte = $cursor->peek();
        if ($byte === '"' || $byte === "'") {
            $literal = $cursor->match(preg_quote($byte, '~') . '(?:\\\\(?:.|\n)|[^\\\\' . $byte . '\n])*' . preg_quote($byte, '~'));
            if ($literal === null) {
                throw SyntaxException::unterminated($byte === '"' ? 'string' : 'character literal', $byte, $start);
            }

            return $literal;
        }
        if ($cursor->startsWith('<%') || $cursor->startsWith('%>')) {
            return $cursor->take(2);
        }

        return $cursor->take(1);
    }
}
