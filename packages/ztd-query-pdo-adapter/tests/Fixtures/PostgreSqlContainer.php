<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\PDO\PDOConnectWaitStrategy;
use Testcontainers\Hook\AfterStartHook;
use Testcontainers\Testcontainers;

/**
 * PostgreSQL container definition for integration tests.
 *
 * Uses AfterStartHook to create and cache a PDO connection on first start.
 * Provides createTestSchema() to create an isolated schema per test.
 */
final class PostgreSqlContainer extends GenericContainer
{
    use AfterStartHook;
    /**
     * @var null|string
     */
    protected static $IMAGE = 'postgres:16';

    /**
     * @var null|string
     */
    protected static $REUSE_MODE = 'reuse';

    /**
     * @var array<int>|null
     */
    protected static $EXPOSED_PORTS = [5432];

    /**
     * @var array<string, string>|null
     */
    protected static $ENVIRONMENTS = [
        'POSTGRES_USER' => 'test',
        'POSTGRES_PASSWORD' => 'test',
        'POSTGRES_DB' => 'ztd_test',
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
            ->withDsn((new PostgreSqlDSN())->withDbname('ztd_test'))
            ->withUsername('test')
            ->withPassword('test')
            ->withTimeoutSeconds(120)
            ->withRetryInterval(250000);
    }

    public function afterStart($instance): void
    {
        $port = $instance->getMappedPort(5432);
        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());

        $pdo = new \PDO(
            "pgsql:host={$host};port={$port};dbname=ztd_test",
            'test',
            'test',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]
        );

        $instance->setData($pdo);
    }

    /**
     * Run the container and create an isolated test schema.
     *
     * @return array{string, \PDO}
     */
    public static function createTestSchema(): array
    {
        $instance = Testcontainers::run(self::class);

        /** @var \PDO $pdo */
        $pdo = $instance->getData(\PDO::class);

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $pdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $pdo->exec(sprintf('SET search_path TO "%s"', $schemaName));

        return [$schemaName, $pdo];
    }
}
