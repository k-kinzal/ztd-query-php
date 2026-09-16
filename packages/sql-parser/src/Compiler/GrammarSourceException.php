<?php

declare(strict_types=1);

namespace SqlParser\Compiler;

use RuntimeException;

/**
 * A grammar source is not written the way its generator would accept it.
 *
 * @visibility root
 */
final class GrammarSourceException extends RuntimeException
{
    /**
     * Describes what was expected at a position of the source.
     *
     * @param string $expected What the reader was looking for
     * @param string $found What it met instead
     * @param int $line Line of the source, counted from one
     *
     * @return self The exception
     */
    public static function unexpected(string $expected, string $found, int $line): self
    {
        return new self("Expected {$expected} but found {$found} on line {$line}");
    }

    /**
     * Describes a construct that never closes.
     *
     * @param string $construct What was opened
     * @param int $line Line of the source where it opened, counted from one
     *
     * @return self The exception
     */
    public static function unterminated(string $construct, int $line): self
    {
        return new self("Unterminated {$construct} opened on line {$line}");
    }
}
