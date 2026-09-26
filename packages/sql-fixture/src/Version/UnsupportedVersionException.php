<?php

declare(strict_types=1);

namespace SqlFixture\Version;

use RuntimeException;

/**
 * A requested database version is not one of the supported releases.
 */
final class UnsupportedVersionException extends RuntimeException
{
    /**
     * @param string $dialect Dialect the version was requested for
     * @param string|null $version Version tag or server version string, or null when the dialect itself has no releases
     */
    public function __construct(public readonly string $dialect, public readonly ?string $version)
    {
        parent::__construct($version === null
            ? sprintf('No supported versions are registered for the %s driver', $dialect)
            : sprintf('Unsupported %s version: %s', $dialect, $version));
    }
}
