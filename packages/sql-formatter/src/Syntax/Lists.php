<?php

declare(strict_types=1);

namespace SqlFormatter\Syntax;

/**
 * Marks a clause's list separators while respecting nested expression delimiters.
 *
 * @visibility SqlFormatter
 */
final class Lists
{
    /**
     * Handles dialect rules that share the generic expression-list production.
     */
    public static function mark(Document $document, int $start, int $end): void
    {
        $depth = 0;
        for ($index = $start; $index <= $end; $index++) {
            $text = $document->tokens[$index]->text;
            if (in_array($text, ['(', '['], true)) {
                $depth++;
            } elseif (in_array($text, [')', ']'], true)) {
                $depth--;
            } elseif ($text === ',' && $depth === 0) {
                $document->commas[$index] = true;
            }
        }
    }
}
