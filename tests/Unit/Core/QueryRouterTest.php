<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\QueryGuard;
use ZtdQuery\QueryRouter;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\ShadowApplier;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Simulator\StatementSimulator;
use Tests\Fixtures\MySqlContainer;
use ZtdQuery\ZteSession;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

final class QueryRouterTest extends TestCase
{
    public function testQueryExecutesPreparedStatement(): void
    {
        $instance = Testcontainers::run(MySqlContainer::class);
        $port = $instance->getMappedPort(3306);
        if (!is_int($port)) {
            $this->fail('MySQL container port mapping missing for 3306.');
        }
        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $admin = new PDO(
            "mysql:host=$host;port=$port;charset=utf8mb4",
            'root',
            'root',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $admin->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));

        $pdo = null;
        try {
            $pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$databaseName;charset=utf8mb4",
                'root',
                'root',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec('CREATE TABLE router_users (id INT PRIMARY KEY, name VARCHAR(255))');
            $pdo->exec("INSERT INTO router_users (id, name) VALUES (1, 'Alice')");

            $shadowStore = new ShadowStore();
            $schemaRegistry = new SchemaRegistry();
            $guard = new QueryGuard();
            $session = new ZteSession(
                $shadowStore,
                $schemaRegistry,
                $guard,
                new FixedRewriter(new RewritePlan('SELECT * FROM router_users', QueryKind::READ)),
                new ShadowApplier($shadowStore),
                new ResultSelectRunner(),
                ZtdConfig::default()
            );
            $router = new QueryRouter($session, new StatementSimulator($session, ZtdConfig::default()));

            $statement = $router->query(
                'SELECT * FROM router_users',
                fn (string $sql): PDOStatement|false => $pdo->prepare($sql),
                PDO::FETCH_ASSOC,
                []
            );

            $this->assertInstanceOf(PDOStatement::class, $statement);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            $this->assertCount(1, $rows);
            $this->assertIsArray($rows[0]);
            $name = $rows[0]['name'] ?? null;
            $this->assertSame('Alice', $name);
            $id = $rows[0]['id'] ?? null;
            if (!is_int($id) && !is_string($id) && !is_float($id)) {
                $this->fail('Expected numeric id value.');
            }
            $this->assertSame(1, (int) $id);
        } finally {
            $pdo = null;
            $admin->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testQueryReturnsFalseWhenPrepareFails(): void
    {
        $shadowStore = new ShadowStore();
        $schemaRegistry = new SchemaRegistry();
        $guard = new QueryGuard();
        $session = new ZteSession(
            $shadowStore,
            $schemaRegistry,
            $guard,
            new FixedRewriter(new RewritePlan('SELECT 1', QueryKind::READ)),
            new ShadowApplier($shadowStore),
            new ResultSelectRunner(),
            ZtdConfig::default()
        );
        $router = new QueryRouter($session, new StatementSimulator($session, ZtdConfig::default()));

        $statement = $router->query(
            'SELECT 1',
            fn (string $sql): bool => false,
            null,
            []
        );

        $this->assertFalse($statement);
    }

    public function testExecUsesSimulatorWhenEnabled(): void
    {
        $instance = Testcontainers::run(MySqlContainer::class);
        $port = $instance->getMappedPort(3306);
        if (!is_int($port)) {
            $this->fail('MySQL container port mapping missing for 3306.');
        }
        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $admin = new PDO(
            "mysql:host=$host;port=$port;charset=utf8mb4",
            'root',
            'root',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $admin->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));

        $pdo = null;
        try {
            $pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$databaseName;charset=utf8mb4",
                'root',
                'root',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec('CREATE TABLE router_demo (id INT)');
            $pdo->exec('INSERT INTO router_demo (id) VALUES (1)');

            $shadowStore = new ShadowStore();
            $schemaRegistry = new SchemaRegistry();
            $guard = new QueryGuard();
            $session = new ZteSession(
                $shadowStore,
                $schemaRegistry,
                $guard,
                new FixedRewriter(new RewritePlan('SELECT * FROM router_demo', QueryKind::READ)),
                new ShadowApplier($shadowStore),
                new ResultSelectRunner(),
                ZtdConfig::default()
            );
            $router = new QueryRouter($session, new StatementSimulator($session, ZtdConfig::default()));
            $session->enable();

            $result = $router->exec(
                'SELECT * FROM router_demo',
                fn (): int => throw new \RuntimeException('exec should not be called'),
                fn (string $sql): PDOStatement|false => $pdo->query($sql)
            );

            $this->assertSame(1, $result);
        } finally {
            $pdo = null;
            $admin->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testExecUsesExecWhenDisabled(): void
    {
        $shadowStore = new ShadowStore();
        $schemaRegistry = new SchemaRegistry();
        $guard = new QueryGuard();
        $session = new ZteSession(
            $shadowStore,
            $schemaRegistry,
            $guard,
            new FixedRewriter(new RewritePlan('SELECT 1', QueryKind::READ)),
            new ShadowApplier($shadowStore),
            new ResultSelectRunner(),
            ZtdConfig::default()
        );
        $router = new QueryRouter($session, new StatementSimulator($session, ZtdConfig::default()));

        $result = $router->exec(
            'SELECT 1',
            fn (): int => 7,
            fn (): PDOStatement|false => throw new \RuntimeException('rawQuery should not be called')
        );

        $this->assertSame(7, $result);
    }

    public function testPrepareDelegatesToPdo(): void
    {
        $instance = Testcontainers::run(MySqlContainer::class);
        $port = $instance->getMappedPort(3306);
        if (!is_int($port)) {
            $this->fail('MySQL container port mapping missing for 3306.');
        }
        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $admin = new PDO(
            "mysql:host=$host;port=$port;charset=utf8mb4",
            'root',
            'root',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $admin->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));

        $pdo = null;
        try {
            $pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$databaseName;charset=utf8mb4",
                'root',
                'root',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $shadowStore = new ShadowStore();
            $schemaRegistry = new SchemaRegistry();
            $guard = new QueryGuard();
            $session = new ZteSession(
                $shadowStore,
                $schemaRegistry,
                $guard,
                new FixedRewriter(new RewritePlan('SELECT 1', QueryKind::READ)),
                new ShadowApplier($shadowStore),
                new ResultSelectRunner(),
                ZtdConfig::default()
            );
            $router = new QueryRouter($session, new StatementSimulator($session, ZtdConfig::default()));

            $statement = $router->prepare('SELECT 1', fn (string $sql): PDOStatement|false => $pdo->prepare($sql));

            $this->assertInstanceOf(PDOStatement::class, $statement);
            $this->assertTrue($statement->execute());
        } finally {
            $pdo = null;
            $admin->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}

final class FixedRewriter implements SqlRewriter
{
    /**
     * Predefined plan to return from rewrite().
     *
     * @var RewritePlan
     */
    private RewritePlan $plan;

    public function __construct(RewritePlan $plan)
    {
        $this->plan = $plan;
    }

    public function rewrite(string $sql): RewritePlan
    {
        return $this->plan;
    }

    public function rewriteMultiple(string $sql): \ZtdQuery\Rewrite\MultiRewritePlan
    {
        return new \ZtdQuery\Rewrite\MultiRewritePlan([$this->plan]);
    }
}
