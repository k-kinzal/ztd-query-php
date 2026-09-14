<?php

declare(strict_types=1);

namespace Containers;

use mysqli;
use Override;
use RuntimeException;
use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\PDO\MySQLDSN;
use Testcontainers\Containers\WaitStrategy\PDO\PDOConnectWaitStrategy;
use Testcontainers\Hook\AfterStartHook;

/**
 * Starts the pinned MySQL 8.4 service used by the package tools.
 */
final class MySql84Container extends GenericContainer
{
    use AfterStartHook;

    /**
     * @var null|string
     */
    protected static $IMAGE = 'mysql:8.4.7';

    /**
     * @var null|string
     */
    protected static $REUSE_MODE = 'restart';

    /**
     * @var array<int>|null
     */
    protected static $EXPOSED_PORTS = [3306];

    /**
     * @var array<string>|null
     */
    protected static $MOUNTS = ['type=tmpfs,destination=/var/lib/mysql'];

    /**
     * @var array<string, string>|null
     */
    protected static $ENVIRONMENTS = [
        'MYSQL_ROOT_PASSWORD' => 'root',
        'MYSQL_INITDB_SKIP_TZINFO' => '1',
    ];

    /**
     * @var null|int
     */
    protected static $STARTUP_TIMEOUT = 300;

    /**
     * @var bool|null
     */
    protected static $AUTO_REMOVE_ON_EXIT = true;

    /**
     * Prepare a fresh database and expose its native connection to the caller.
     *
     * @throws RuntimeException If MySQL has no mapped port.
     */
    public function afterStart($instance): void
    {
        $port = $instance->getMappedPort(3306) ?? throw new RuntimeException('MySQL port was not mapped.');
        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());
        $connection = new mysqli($host, 'root', 'root', '', $port);
        $connection->query('CREATE DATABASE test CHARACTER SET utf8mb4');
        $connection->select_db('test');
        $connection->set_charset('utf8mb4');
        $instance->setData($connection);
    }

    #[Override]
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
