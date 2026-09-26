<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Reporter;

use RuntimeException;

/**
 * A reporter was asked for by a name nothing is registered under.
 *
 * @visibility root
 */
final class UnknownReporterException extends RuntimeException
{
    /**
     * @param string $name The name that was asked for
     * @param list<string> $available The names that are registered
     */
    public function __construct(
        public readonly string $name,
        public readonly array $available,
    ) {
        parent::__construct(sprintf(
            'Unknown reporter "%s". Available reporters: %s.',
            $name,
            $available === [] ? 'none' : implode(', ', $available),
        ));
    }
}
