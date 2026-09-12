<?php

declare(strict_types=1);

namespace Fuzz\Container;

use Override;
use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\PDO\MySQLDSN;
use Testcontainers\Containers\WaitStrategy\PDO\PDOConnectWaitStrategy;

/**
 * Testcontainers definition for the MySQL 9.1.0 server the fuzzer runs against.
 *
 * The image tag is pinned so that a finding always reproduces against the
 * same server build, and each run receives a disposable container. The same server
 * is used by every input within that run.
 */
final class MySql91Container extends GenericContainer
{
    /**
     * @var null|string
     */
    protected static $IMAGE = 'container-registry.oracle.com/mysql/community-server:9.1.0';

    /**
     * @var null|string
     */
    protected static $REUSE_MODE = 'add';

    /**
     * @var array<int>|null
     */
    protected static $EXPOSED_PORTS = [3306];

    /**
     * @var array<string, string>|null
     */
    protected static $ENVIRONMENTS = [
        'MYSQL_ROOT_PASSWORD' => 'root',
        'MYSQL_ROOT_HOST' => '%',
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

    /**
     * Names the grammar version matching this container's server version.
     *
     * @return string Grammar version identifier, e.g. "mysql-9.1.0"
     */
    public static function getGrammarVersion(): string
    {
        return 'mysql-9.1.0';
    }
}
