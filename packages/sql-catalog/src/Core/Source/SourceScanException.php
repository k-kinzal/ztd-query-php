<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Source;

use RuntimeException;

/**
 * A path the scanner was asked to read and could not.
 *
 * @visibility root
 */
final class SourceScanException extends RuntimeException
{
    /**
     * @param string $path The path that could not be read
     * @param string $reason Why it could not be read
     */
    public function __construct(
        public readonly string $path,
        string $reason,
    ) {
        parent::__construct(sprintf('Cannot read "%s": %s.', $path, $reason));
    }
}
