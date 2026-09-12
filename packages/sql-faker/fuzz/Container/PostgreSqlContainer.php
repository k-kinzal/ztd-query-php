<?php

declare(strict_types=1);

namespace Fuzz\Container;

use Override;
use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\PDO\PDOConnectWaitStrategy;

/**
 * Testcontainers definition for the PostgreSQL server the fuzzer runs against.
 *
 * The image tag is pinned so that a finding always reproduces against the
 * same server build, and each run receives a disposable container. The same server
 * is used by every input within that run.
 */
final class PostgreSqlContainer extends GenericContainer
{
    /**
     * @var null|string
     */
    protected static $IMAGE = 'postgres:17.2';

    /**
     * @var null|string
     */
    protected static $REUSE_MODE = 'add';

    /**
     * @var bool|null
     */
    protected static $AUTO_REMOVE_ON_EXIT = true;

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

    #[Override]
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
