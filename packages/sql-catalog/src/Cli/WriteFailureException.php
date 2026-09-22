<?php

declare(strict_types=1);

namespace SqlCatalog\Cli;

use RuntimeException;

/**
 * A report could not be written where it was asked to go.
 *
 * @visibility root
 */
final class WriteFailureException extends RuntimeException
{
    /**
     * @param string $path The path that could not be written
     * @param string $reason Why it could not be written
     */
    public function __construct(
        public readonly string $path,
        string $reason,
    ) {
        parent::__construct(sprintf('Cannot write "%s": %s.', $path, $reason));
    }
}
