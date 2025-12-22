<?php

declare(strict_types=1);

namespace Tests\Integration;

use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\ZtdConfig;
use Tests\Fixtures\MySqlContainer;
use PDO;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

/**
 * Base test case for MySQL PDO integration tests.
 *
 * Provides shared container management and database setup utilities.
 */
abstract class MySqlIntegrationTestCase extends TestCase
{
    private string $databaseName;

    /**
     * Raw PDO connection to the test database.
     */
    protected PDO $rawPdo;

    /**
     * ZTD-enabled PDO connection (wraps rawPdo).
     */
    protected ZtdPdo $ztdPdo;

    /**
     * Override to provide custom ZTD configuration.
     */
    protected function getZtdConfig(): ?ZtdConfig
    {
        return null;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $instance = Testcontainers::run(MySqlContainer::class);
        $port = $instance->getMappedPort(3306);
        if (!is_int($port)) {
            $this->fail('MySQL container port mapping missing for 3306.');
        }

        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());
        $this->databaseName = 'ztd_' . bin2hex(random_bytes(8));

        $this->rawPdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            'root',
            'root',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->rawPdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $this->databaseName));
        $this->rawPdo->exec(sprintf('USE `%s`', $this->databaseName));

        $this->ztdPdo = ZtdPdo::fromPdo($this->rawPdo, $this->getZtdConfig());
        $this->ztdPdo->enableZtd();
    }

    protected function tearDown(): void
    {
        // Drop database and close connection
        $this->rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $this->databaseName));

        parent::tearDown();
    }

    /**
     * Generate a unique table name for test isolation.
     */
    protected function uniqueTableName(string $prefix = 'test'): string
    {
        return $prefix . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Execute a query on ztdPdo and return all rows.
     *
     * @return list<array<string, mixed>>
     */
    protected function ztdQuery(string $sql): array
    {
        $stmt = $this->ztdPdo->query($sql);
        if ($stmt === false) {
            $this->fail("Query failed: {$sql}");
        }
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Execute a query on rawPdo and return all rows.
     *
     * @return list<array<string, mixed>>
     */
    protected function rawQuery(string $sql): array
    {
        $stmt = $this->rawPdo->query($sql);
        if ($stmt === false) {
            $this->fail("Query failed: {$sql}");
        }
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Create a new ZtdPdo instance with the specified configuration.
     *
     * Useful for testing different UnsupportedSqlBehavior modes within the same test class.
     */
    protected function createZtdPdoWithConfig(ZtdConfig $config): ZtdPdo
    {
        $pdo = ZtdPdo::fromPdo($this->rawPdo, $config);
        $pdo->enableZtd();
        return $pdo;
    }
}
