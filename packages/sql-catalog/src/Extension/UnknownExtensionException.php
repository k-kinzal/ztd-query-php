<?php

declare(strict_types=1);

namespace SqlCatalog\Extension;

use RuntimeException;

/**
 * An extension was asked for by a name nothing is registered under.
 *
 * @visibility root
 */
final class UnknownExtensionException extends RuntimeException
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
            'Unknown extension "%s". Available extensions: %s.',
            $name,
            $available === [] ? 'none' : implode(', ', $available),
        ));
    }
}
