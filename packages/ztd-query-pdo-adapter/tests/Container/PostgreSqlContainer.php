<?php

declare(strict_types=1);

namespace Tests\Container;

use Override;
use PDO;
use Testcontainers\Containers\GenericContainer\GenericContainer;
use Testcontainers\Containers\WaitStrategy\LogMessageWaitStrategy;
use Testcontainers\Hook\AfterStartHook;

/**
 * PostgreSQL container definition for integration tests.
 *
 * Uses AfterStartHook to create and cache a PDO connection on first start.
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
     * Parallel PHPUnit workers can select the same seeded port candidates.
     *
     * @var null|int
     */
    protected static $STARTUP_CONFLICT_RETRY_ATTEMPTS = 10;

    /**
     * @var bool|null
     */
    protected static $AUTO_REMOVE_ON_EXIT = true;

    #[Override]
    protected function waitStrategy($instance): LogMessageWaitStrategy
    {
        unset($instance);

        return (new LogMessageWaitStrategy())
            ->withPattern('\\[1\\].*database system is ready to accept connections')
            ->withTimeoutSeconds(120);
    }

    /**
     * After start.
     *
     */
    public function afterStart($instance): void
    {
        $port = $instance->getMappedPort(5432);
        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());

        $pdo = new PDO(
            "pgsql:host={$host};port={$port};dbname=ztd_test",
            'test',
            'test',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        $instance->setData($pdo);
    }

}
