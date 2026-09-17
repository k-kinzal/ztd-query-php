<?php

declare(strict_types=1);

namespace Container;

use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\ReuseMode;
use Testcontainers\Containers\WaitStrategy\PDO\MySQLDSN;
use Testcontainers\Containers\WaitStrategy\PDO\PDOConnectWaitStrategy;
use Testcontainers\Exceptions\InvalidFormatException;

/**
 * Applies the shared MySQL startup and readiness settings.
 */
final class MySqlConfiguration
{
    /**
     * Configures a reusable MySQL server with a disposable test database.
     *
     * @param GenericContainer $container Container definition to configure.
     * @throws InvalidFormatException If a configured mount cannot be parsed.
     */
    public function apply(GenericContainer $container): void
    {
        $container
            ->withReuseMode(ReuseMode::REUSE())
            ->withExposedPorts([3306])
            ->withMounts(['type=tmpfs,destination=/var/lib/mysql'])
            ->withEnvs([
                'MYSQL_ROOT_PASSWORD' => 'root',
                'MYSQL_ROOT_HOST' => '%',
                'MYSQL_DATABASE' => 'test',
                'MYSQL_INITDB_SKIP_TZINFO' => '1',
            ])
            ->withStartupTimeout(300)
            ->withStartupConflictRetries(10)
            ->withAutoRemoveOnExit(true)
            ->withWaitStrategy((new PDOConnectWaitStrategy())
                ->withDsn((new MySQLDSN())->withDbname('test')->withCharset('utf8mb4'))
                ->withUsername('root')
                ->withPassword('root')
                ->withTimeoutSeconds(120)
                ->withRetryInterval(250000));
    }
}
