<?php

declare(strict_types=1);

namespace SqlFixture\Syntax;

/**
 * Strips the delimiters of a quoted lexeme and unfolds doubled delimiters.
 *
 * @visibility root
 */
final class QuotedText
{
    /**
     * Returns the text between the delimiters, or the lexeme itself when it is not delimited.
     */
    public function unquote(string $text): string
    {
        $opening = substr($text, 0, 1);
        $closing = $opening === '[' ? ']' : $opening;
        if (strlen($text) < 2 || !str_ends_with($text, $closing)) {
            return $text;
        }
        $inner = substr($text, 1, -1);

        return $opening === '[' ? $inner : str_replace($closing . $closing, $closing, $inner);
    }
}
