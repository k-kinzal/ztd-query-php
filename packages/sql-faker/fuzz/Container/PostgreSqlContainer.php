<?php

declare(strict_types=1);

namespace Fuzz\Container;

use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\PDO\PDOConnectWaitStrategy;

/**
 * Reusable Testcontainers definition for the PostgreSQL 16 fuzz target.
 */
final class PostgreSqlContainer extends GenericContainer
{
    /**
     * @var null|string
     */
    protected static $IMAGE = 'postgres:16';

    /**
     * @var null|string
     */
    protected static $REUSE_MODE = 'reuse';

    /**
     * @var array<int>|null
     */
    protected static $EXPOSED_PORTS = [5432];

    /**
     * @var array<string, string>|null
     */
    protected static $ENVIRONMENTS = [
        'POSTGRES_USER' => 'test',
        'POSTGRES_PASSWORD' => 'test',
        'POSTGRES_DB' => 'fuzz_test',
    ];

    /**
     * @var null|int
     */
    protected static $STARTUP_TIMEOUT = 300;

    protected function waitStrategy($instance): PDOConnectWaitStrategy
    {
        unset($instance);

        return (new PDOConnectWaitStrategy())
            ->withDsn((new PostgreSqlDSN())->withDbname('fuzz_test'))
            ->withUsername('test')
            ->withPassword('test')
            ->withTimeoutSeconds(120)
            ->withRetryInterval(250000);
    }
}
