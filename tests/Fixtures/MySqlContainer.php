<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\PDO\MySQLDSN;
use Testcontainers\Containers\WaitStrategy\PDO\PDOConnectWaitStrategy;

/**
 * MySQL container definition for integration tests.
 *
 * This container uses a PDO wait strategy so that Testcontainers only returns
 * after MySQL is actually ready to accept connections.
 */
final class MySqlContainer extends GenericContainer
{
    /**
     * @var null|string
     */
    protected static $IMAGE = 'mysql:8.0';

    /**
     * @var null|string
     */
    protected static $REUSE_MODE = 'reuse';

    /**
     * @var list<int>
     */
    protected static $EXPOSED_PORTS = [3306];

    /**
     * @var array<string, string>
     */
    protected static $ENVIRONMENTS = [
        'MYSQL_ROOT_PASSWORD' => 'root',
    ];

    /**
     * @var null|int
     */
    protected static $STARTUP_TIMEOUT = 300;

    protected function waitStrategy($instance): PDOConnectWaitStrategy
    {
        unset($instance);

        return (new PDOConnectWaitStrategy())
            ->withDsn((new MySQLDSN())->withCharset('utf8mb4'))
            ->withUsername('root')
            ->withPassword('root')
            ->withTimeoutSeconds(120)
            ->withRetryInterval(250000);
    }
}
