<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\PDO\MySQLDSN;
use Testcontainers\Containers\WaitStrategy\PDO\PDOConnectWaitStrategy;
use Testcontainers\Hook\AfterStartHook;
use Testcontainers\Testcontainers;

/**
 * MySQL container definition for integration tests.
 *
 * Uses AfterStartHook to create and cache a mysqli connection on first start.
 * Provides createTestDatabase() to create an isolated database per test.
 */
final class MySqlContainer extends GenericContainer
{
    use AfterStartHook;
    /**
     * @var null|string
     */
    protected static $IMAGE = 'mysql:8.0';

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

    public function afterStart($instance): void
    {
        $port = $instance->getMappedPort(3306);
        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());

        $mysqli = new \mysqli($host, 'root', 'root', '', $port);
        $mysqli->set_charset('utf8mb4');

        $instance->setData($mysqli);
    }

    /**
     * Run the container and create an isolated test database.
     *
     * @return array{string, \mysqli}
     */
    public static function createTestDatabase(): array
    {
        $instance = Testcontainers::run(self::class);

        /** @var \mysqli $mysqli */
        $mysqli = $instance->getData(\mysqli::class);

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $mysqli->query(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));
        $mysqli->select_db($databaseName);

        return [$databaseName, $mysqli];
    }
}
