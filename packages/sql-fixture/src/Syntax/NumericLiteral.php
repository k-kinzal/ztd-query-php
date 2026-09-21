<?php

declare(strict_types=1);

namespace SqlFixture\Syntax;

/**
 * Converts the text of a numeric token into the PHP number it denotes.
 *
 * @visibility root
 */
final class NumericLiteral
{
    /**
     * Returns an integer for whole numbers that fit the platform, and a float otherwise.
     */
    public function decode(string $text): int|float
    {
        $integer = filter_var($text, FILTER_VALIDATE_INT);

        return $integer === false ? (float) $text : $integer;
    }
}
