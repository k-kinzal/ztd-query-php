<?php

declare(strict_types=1);

namespace Container;

use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\LogMessageWaitStrategy;

abstract class PostgreSqlContainer extends GenericContainer
{
    /** @var null|string */
    protected static $REUSE_MODE = 'reuse';

    /** @var array<int>|null */
    protected static $EXPOSED_PORTS = [5432];

    /** @var array<string, string>|null */
    protected static $ENVIRONMENTS = [
        'POSTGRES_USER' => 'test',
        'POSTGRES_PASSWORD' => 'test',
        'POSTGRES_DB' => 'test',
    ];

    /** @var null|int */
    protected static $STARTUP_TIMEOUT = 300;

    /** @var null|int */
    protected static $STARTUP_CONFLICT_RETRY_ATTEMPTS = 10;

    /** @var bool|null */
    protected static $AUTO_REMOVE_ON_EXIT = true;

    protected function waitStrategy($instance): LogMessageWaitStrategy
    {
        unset($instance);

        return (new LogMessageWaitStrategy())
            ->withPattern('\\[1\\].*database system is ready to accept connections')
            ->withTimeoutSeconds(120);
    }
}
