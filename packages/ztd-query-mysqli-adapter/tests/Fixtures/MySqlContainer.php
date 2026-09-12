<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use mysqli;
use Override;
use RuntimeException;
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
    protected static $IMAGE = 'mysql:8.0.44';

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
     * Connect to the started container and cache its native handle.
     *
     * @throws RuntimeException If the MySQL port is not mapped.
     */
    public function afterStart($instance): void
    {
        $port = $instance->getMappedPort(3306) ?? throw new RuntimeException('MySQL port was not mapped.');
        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());

        $mysqli = new mysqli($host, 'root', 'root', '', $port);
        $mysqli->set_charset('utf8mb4');

        $instance->setData($mysqli);
    }

    /**
     * Run the container and create an isolated test database.
     *
     * @return array{string, mysqli}
     * @throws RuntimeException If the configured port is invalid.
     */
    public static function createTestDatabase(): array
    {
        $mysqli = new mysqli(...self::connectionParameters());
        $mysqli->set_charset('utf8mb4');

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $mysqli->query(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));
        $mysqli->select_db($databaseName);

        return [$databaseName, $mysqli];
    }
    /**
     * Resolve the local service or disposable container used by native fixtures.
     *
     * @return array{string, string, string, string, int}
     * @throws RuntimeException If the configured port is invalid.
     */
    public static function connectionParameters(): array
    {
        $host = getenv('MYSQL_HOST');
        if ($host !== false) {
            $configuredPort = getenv('MYSQL_PORT');
            $port = filter_var($configuredPort === false ? '3306' : $configuredPort, FILTER_VALIDATE_INT);
            if ($port === false) {
                throw new RuntimeException('MYSQL_PORT must be an integer.');
            }
            return [$host, 'root', 'root', '', $port];
        }
        $instance = Testcontainers::run(self::class);
        return [str_replace('localhost', '127.0.0.1', $instance->getHost()), 'root', 'root', '', $instance->getMappedPort(3306) ?? throw new RuntimeException('MySQL port was not mapped.')];


    }
}
