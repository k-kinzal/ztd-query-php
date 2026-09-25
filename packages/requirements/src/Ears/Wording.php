<?php

declare(strict_types=1);

namespace Requirements\Ears;

/**
 * Decides whether clause text says anything.
 */
final class Wording
{
    /**
     * Tells whether the text contains a letter or a digit.
     *
     * @param string $text The clause text
     *
     * @return bool True when the text has content beyond punctuation and space
     */
    public static function hasContent(string $text): bool
    {
        return preg_match('/[\p{L}\p{N}]/u', $text) === 1;
    }
}
