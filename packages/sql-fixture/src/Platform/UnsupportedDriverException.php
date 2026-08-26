<?php

declare(strict_types=1);

namespace SqlFixture\Platform;

use RuntimeException;

/**
 * Reports a database this package has no support for.
 *
 * Which servers are supported is a fact about this package, and a caller
 * pointing it at a connection to something else is asking a question it cannot
 * answer rather than making a mistake in how it asked. Naming the refusal
 * lets a caller that supports more databases than this one catch it and fall
 * back, which a bare argument exception would not.
 */
final class UnsupportedDriverException extends RuntimeException
{
    /**
     * Reports a driver name this package does not support.
     *
     * @param string $driver Driver the caller named
     *
     * @return self Exception naming the driver
     */
    public static function named(string $driver): self
    {
        return new self("Unsupported driver: {$driver}");
    }

    /**
     * Reports a connection that will not say which driver it uses.
     *
     * @return self Exception saying the driver could not be detected
     */
    public static function undetectable(): self
    {
        return new self('Unable to detect PDO driver');
    }
}
