<?php

declare(strict_types=1);

namespace SqlFormatter\Syntax;

/**
 * Pairs parentheses and identifies nested query blocks.
 *
 * @visibility SqlFormatter
 */
final class Brackets
{
    /**
     * Pairs delimiters and marks parentheses containing a query.
     */
    public static function mark(Document $document): void
    {
        $stack = [];
        foreach ($document->tokens as $index => $token) {
            if ($token->text === '(') {
                $stack[] = $index;
            } elseif ($token->text === ')' && $stack !== []) {
                $open = array_pop($stack);
                $document->pairs[$open] = $index;
            }
        }
        foreach ($document->pairs as $open => $close) {
            if (isset($document->clauses[$open + 1])) {
                $document->blocks[$open] = true;
            }
        }
    }
}
