<?php

declare(strict_types=1);

namespace Container;

use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Exceptions\InvalidFormatException;

/**
 * Defines the MySQL 5.7.44 server used by database consumers.
 *
 * @visibility public
 * @example Inspect the pinned server image without starting Docker
 *     $container = new \Container\MySql57Container();
 *     assert($container->image() === 'mysql:5.7.44');
 */
final class MySql57Container extends GenericContainer
{
    /**
     * Creates the pinned MySQL definition with shared startup settings.
     *
     * @throws InvalidFormatException If a configured mount cannot be parsed.
     */
    public function __construct()
    {
        parent::__construct('mysql:5.7.44');
        (new MySqlConfiguration())->apply($this);
    }

    /**
     * Returns the SQL grammar identifier matching the server version.
     *
     * @return string Matching SQL grammar identifier.
     */
    public static function getGrammarVersion(): string
    {
        return 'mysql-5.7.44';
    }
}
