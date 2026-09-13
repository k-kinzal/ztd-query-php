<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Override;
use PDO;

/**
 * A connection that answers whatever driver name a test needs it to.
 *
 * Which platform ZTD picks is read off the connection, and a test cannot make
 * a connection to every driver ZTD supports. This is a real SQLite connection
 * that says it speaks something else.
 */
final class DriverNamePdo extends PDO
{
    /**
     * Opens a connection that reports the given driver name.
     *
     * @param string $driverName Name the connection is to report
     */
    public function __construct(private readonly string $driverName)
    {
        parent::__construct('sqlite::memory:');
    }

    /**
     * {@inheritDoc}
     *
     * @return mixed The driver name this was built with, or what SQLite says
     */
    #[Override]
    public function getAttribute(int $attribute): mixed
    {
        if ($attribute === PDO::ATTR_DRIVER_NAME) {
            return $this->driverName;
        }

        return parent::getAttribute($attribute);
    }
}
