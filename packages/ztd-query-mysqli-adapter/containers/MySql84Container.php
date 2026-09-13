<?php

declare(strict_types=1);

namespace Containers;

use Override;
use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\PDO\MySQLDSN;
use Testcontainers\Containers\WaitStrategy\PDO\PDOConnectWaitStrategy;

/**
 * Starts the pinned MySQL 8.4 service used by the package tools.
 */
final class MySql84Container extends GenericContainer
{
    /**
     * @var null|string
     */
    protected static $IMAGE = 'mysql:8.4.7';

    /**
     * @var null|string
     */
    protected static $REUSE_MODE = 'reuse';

    /**
     * @var array<int>|null
     */
    protected static $EXPOSED_PORTS = [3306];

    /**
     * @var list<string>|null
     */
    protected static $MOUNTS = ['type=tmpfs,destination=/var/lib/mysql'];

    /**
     * @var array<string, string>|null
     */
    protected static $ENVIRONMENTS = [
        'MYSQL_ROOT_PASSWORD' => 'root',
        'MYSQL_DATABASE' => 'test',
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
