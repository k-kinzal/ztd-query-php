<?php

declare(strict_types=1);

namespace LemonParser\Scanner;

use LemonParser\SyntaxException;

/**
 * Reads a `{ ... }` block as Lemon's tokenizer does.
 *
 * Braces nest, and braces inside comments, strings and character literals
 * do not count. Backslashes escape inside strings and character literals.
 *
 * @visibility root
 */
final class CodeReader
{
    /**
     * Reads the block the cursor is at and returns the text between the braces.
     *
     * @param Cursor $cursor Positioned at the opening brace
     *
     * @return string The code without its braces
     *
     * @throws SyntaxException When the file ends before the closing brace
     */
    public function read(Cursor $cursor): string
    {
        $start = $cursor->location();
        $cursor->take(1);
        $from = $cursor->offset();
        $level = 1;
        while (!$cursor->eof()) {
            $byte = $cursor->peek();
            if ($byte === '}' && $level === 1) {
                $code = substr($cursor->source, $from, $cursor->offset() - $from);
                $cursor->take(1);

                return $code;
            }
            $level += match ($byte) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };
            if ($byte === '/' && ($cursor->peek(1) === '*' || $cursor->peek(1) === '/')) {
                $this->comment($cursor);
            } elseif ($byte === "'" || $byte === '"') {
                $this->literal($cursor);
            } else {
                $cursor->take(1);
            }
        }
        throw new SyntaxException('C code starting on this line is not terminated before the end of the file.', $start);
    }

    /**
     * Moves past a comment inside code.
     *
     * @param Cursor $cursor Positioned at the slash
     */
    public function comment(Cursor $cursor): void
    {
        if ($cursor->peek(1) === '/') {
            $cursor->match('//[^\n]*\n?');

            return;
        }
        $cursor->take(2);
        if ($cursor->takeUntil('*/') === null) {
            $cursor->take(strlen($cursor->source));
        }
    }

    /**
     * Moves past a string or character literal inside code.
     *
     * @param Cursor $cursor Positioned at the opening quote
     */
    public function literal(Cursor $cursor): void
    {
        $quote = $cursor->take(1);
        $escaped = false;
        while (!$cursor->eof()) {
            $byte = $cursor->take(1);
            if ($byte === $quote && !$escaped) {
                return;
            }
            $escaped = $byte === '\\' && !$escaped;
        }
    }
}
