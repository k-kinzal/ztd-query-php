<?php

declare(strict_types=1);

namespace Fuzz\Container;

use Override;
use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\LogMessageWaitStrategy;

/**
 * The postgre sql container.
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

    #[Override]
    protected function waitStrategy($instance): LogMessageWaitStrategy
    {
        unset($instance);

        return (new LogMessageWaitStrategy())
            ->withPattern('\\[1\\].*database system is ready to accept connections')
            ->withTimeoutSeconds(120);
    }
}
