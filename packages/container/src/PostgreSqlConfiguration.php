<?php

declare(strict_types=1);

namespace Container;

use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\ReuseMode;
use Testcontainers\Containers\WaitStrategy\LogMessageWaitStrategy;

/**
 * Applies the shared PostgreSQL startup and readiness settings.
 */
final class PostgreSqlConfiguration
{
    /**
     * Configures a reusable PostgreSQL server with a disposable test database.
     *
     * @param GenericContainer $container Container definition to configure.
     */
    public function apply(GenericContainer $container): void
    {
        $container
            ->withReuseMode(ReuseMode::REUSE())
            ->withExposedPorts([5432])
            ->withEnvs([
                'POSTGRES_USER' => 'test',
                'POSTGRES_PASSWORD' => 'test',
                'POSTGRES_DB' => 'test',
            ])
            ->withStartupTimeout(300)
            ->withStartupConflictRetries(10)
            ->withAutoRemoveOnExit(true)
            ->withWaitStrategy((new LogMessageWaitStrategy())
                ->withPattern('\\[1\\].*database system is ready to accept connections')
                ->withTimeoutSeconds(120));
    }
}
