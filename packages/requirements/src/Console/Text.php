<?php

declare(strict_types=1);

namespace Requirements\Console;

use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Makes report values safe to write to a terminal.
 */
final class Text
{
    /**
     * Converts a report value to escaped plain text.
     *
     * @param mixed $value A string or number; anything else becomes an empty string
     *
     * @return string The text without control characters, with console markup escaped
     */
    public static function plain(mixed $value): string
    {
        $text = is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
        $text = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $text) ?? '';
        return OutputFormatter::escape($text);
    }
}
