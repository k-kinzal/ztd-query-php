<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Session;

/**
 * Factory for creating Session instances configured for a specific database platform.
 *
 * Each platform package provides a concrete implementation
 * (e.g. MySqlSessionFactory, PostgresSessionFactory, SqliteSessionFactory).
 */
interface SessionFactory
{
    /**
     * Create a Session pre-configured for the platform.
     *
     * The implementation MUST:
     * - Reflect all existing table schemas from the database via SchemaReflector
     * - Return a Session with isEnabled() === true
     * - Return a Session with an empty ShadowStore
     *
     * If schema reflection fails (connection error, permission error), the implementation
     * MUST propagate the exception. Partial schema loading is NOT permitted.
     *
     * @param ConnectionInterface $connection An active database connection.
     * @param ZtdConfig $config ZTD configuration.
     * @return Session A fully configured session.
     * @throws \ZtdQuery\Connection\Exception\DatabaseException If schema reflection fails.
     */
    public function create(ConnectionInterface $connection, ZtdConfig $config): Session;
}
