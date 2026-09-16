<?php

declare(strict_types=1);

namespace Container;

use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\PDO\MySQLDSN;
use Testcontainers\Containers\WaitStrategy\PDO\PDOConnectWaitStrategy;

abstract class MySqlContainer extends GenericContainer
{
    /** @var null|string */
    protected static $REUSE_MODE = 'reuse';

    /** @var array<int>|null */
    protected static $EXPOSED_PORTS = [3306];

    /** @var array<string>|null */
    protected static $MOUNTS = ['type=tmpfs,destination=/var/lib/mysql'];

    /** @var array<string, string>|null */
    protected static $ENVIRONMENTS = [
        'MYSQL_ROOT_PASSWORD' => 'root',
        'MYSQL_ROOT_HOST' => '%',
        'MYSQL_DATABASE' => 'test',
        'MYSQL_INITDB_SKIP_TZINFO' => '1',
    ];

    /** @var null|int */
    protected static $STARTUP_TIMEOUT = 300;

    /** @var null|int */
    protected static $STARTUP_CONFLICT_RETRY_ATTEMPTS = 10;

    /** @var bool|null */
    protected static $AUTO_REMOVE_ON_EXIT = true;

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

    abstract public static function getGrammarVersion(): string;
}
