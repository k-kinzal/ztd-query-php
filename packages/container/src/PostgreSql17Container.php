<?php

declare(strict_types=1);

namespace Container;

use Testcontainers\Containers\GenericContainer\GenericContainer;

/**
 * Defines the PostgreSQL 17.2 server used by database consumers.
 *
 * @visibility public
 * @example Inspect the pinned server image without starting Docker
 *     $container = new \Container\PostgreSql17Container();
 *     assert($container->image() === 'postgres:17.2');
 */
final class PostgreSql17Container extends GenericContainer
{
    /**
     * Creates the pinned PostgreSQL definition with shared startup settings.
     */
    public function __construct()
    {
        parent::__construct('postgres:17.2');
        (new PostgreSqlConfiguration())->apply($this);
    }
}
