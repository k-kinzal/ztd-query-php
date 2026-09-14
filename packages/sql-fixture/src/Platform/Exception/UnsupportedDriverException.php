<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Exception;

use InvalidArgumentException;

/**
 * A requested database driver has no platform implementation.
 */
final class UnsupportedDriverException extends InvalidArgumentException
{
    /**
     * A requested database driver has no platform implementation.
     */
    public function __construct(public readonly string $driver)
    {
        parent::__construct(sprintf('Unsupported database driver: %s', $driver));
    }
}
