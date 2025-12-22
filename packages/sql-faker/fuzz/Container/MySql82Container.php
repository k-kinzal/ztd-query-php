<?php

declare(strict_types=1);

namespace Fuzz\Container;

use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\PDO\MySQLDSN;
use Testcontainers\Containers\WaitStrategy\PDO\PDOConnectWaitStrategy;

final class MySql82Container extends GenericContainer
{
    /**
     * @var null|string
     */
    protected static $IMAGE = 'mysql:8.2.0';

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

    public static function getGrammarVersion(): string
    {
        return 'mysql-8.2.0';
    }
}
