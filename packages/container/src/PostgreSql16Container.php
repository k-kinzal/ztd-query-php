<?php

declare(strict_types=1);

namespace Container;

use Testcontainers\Containers\GenericContainer\GenericContainer;

/**
 * Defines the PostgreSQL 16 server used by database consumers.
 *
 * @visibility public
 * @example Inspect the pinned server image without starting Docker
 *     $container = new \Container\PostgreSql16Container();
 *     assert($container->image() === 'postgres:16');
 */
final class PostgreSql16Container extends GenericContainer
{
    /**
     * Creates the pinned PostgreSQL definition with shared startup settings.
     */
    public function __construct()
    {
        parent::__construct('postgres:16');
        (new PostgreSqlConfiguration())->apply($this);
    }
}
