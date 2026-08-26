<?php

declare(strict_types=1);

namespace Fuzz\Container;

use Override;
use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\PDO\MySQLDSN;
use Testcontainers\Containers\WaitStrategy\PDO\PDOConnectWaitStrategy;

/**
 * Starts the MySQL release the fuzz run is exercised against.
 *
 * A fuzz target that generates SQL from one release's grammar has to run it
 * against that release: a statement MySQL 8.4 accepts is not necessarily one
 * an older server parses, and a mismatch would report the server's age as a
 * bug in this package.
 */
final class MySql84Container extends GenericContainer
{
    /**
     * @var null|string
     */
    protected static $IMAGE = 'container-registry.oracle.com/mysql/community-server:8.4.7';

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
        'MYSQL_ROOT_HOST' => '%',
        'MYSQL_DATABASE' => 'test',
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
            ->withDsn((new MySQLDSN())->withDbname('test')->withCharset('utf8mb4'))
            ->withUsername('root')
            ->withPassword('root')
            ->withTimeoutSeconds(120)
            ->withRetryInterval(250000);
    }

    /**
     * Answers the grammar release the generated SQL should be read against.
     *
     * @return string Release tag sql-faker names this server by
     */
    public static function getGrammarVersion(): string
    {
        return 'mysql-8.4.7';
    }
}
