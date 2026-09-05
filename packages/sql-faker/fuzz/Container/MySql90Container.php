<?php

declare(strict_types=1);

namespace Fuzz\Container;

use Override;
use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\PDO\MySQLDSN;
use Testcontainers\Containers\WaitStrategy\PDO\PDOConnectWaitStrategy;

/**
 * Testcontainers definition for the MySQL 9.0.1 server the fuzzer runs against.
 *
 * The image tag is pinned so that a finding always reproduces against the
 * same server build, and the container is reused across runs to keep
 * start-up cost off every fuzzing iteration.
 */
final class MySql90Container extends GenericContainer
{
    /**
     * @var null|string
     */
    protected static $IMAGE = 'container-registry.oracle.com/mysql/community-server:9.0.1';

    /**
     * @var null|string
     */
    protected static $REUSE_MODE = 'reuse';

    /**
     * @var array<int>|null
     */
    protected static $EXPOSED_PORTS = [3306];

    /**
     * @var array<string, string>|null
     */
    protected static $ENVIRONMENTS = [
        'MYSQL_ROOT_PASSWORD' => 'root',
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
     * @return string Grammar version identifier, e.g. "mysql-9.0.1"
     */
    public static function getGrammarVersion(): string
    {
        return 'mysql-9.0.1';
    }
}
