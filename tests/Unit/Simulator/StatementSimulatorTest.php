<?php

declare(strict_types=1);

namespace Tests\Unit\Simulator;

use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\QueryGuard;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\ShadowApplier;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Simulator\StatementSimulator;
use Tests\Fixtures\MySqlContainer;
use ZtdQuery\ZteSession;
use PDO;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

final class StatementSimulatorTest extends TestCase
{
    public function testForbiddenStatementThrows(): void
    {
        $shadowStore = new ShadowStore();
        $schemaRegistry = new SchemaRegistry();
        $guard = new QueryGuard();
        $session = new ZteSession(
            $shadowStore,
            $schemaRegistry,
            $guard,
            new FixedRewriter(new RewritePlan('DROP TABLE users', QueryKind::FORBIDDEN)),
            new ShadowApplier($shadowStore),
            new ResultSelectRunner(),
            ZtdConfig::default()
        );
        $simulator = new StatementSimulator($session, ZtdConfig::default());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ZTD Write Protection');

        $simulator->simulate('DROP TABLE users', fn () => false);
    }

    public function testReadStatementReturnsRowCount(): void
    {
        $instance = Testcontainers::run(MySqlContainer::class);
        $port = $instance->getMappedPort(3306);
        if (!is_int($port)) {
            $this->fail('MySQL container port mapping missing for 3306.');
        }
        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());

        $pdo = new PDO(
            "mysql:host=$host;port=$port;charset=utf8mb4",
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
            new FixedRewriter(new RewritePlan('SELECT 1 AS id', QueryKind::READ)),
            new ShadowApplier($shadowStore),
            new ResultSelectRunner(),
            ZtdConfig::default()
        );
        $simulator = new StatementSimulator($session, ZtdConfig::default());

        $result = $simulator->simulate('SELECT 1 AS id', fn (string $sql) => $pdo->query($sql));

        $this->assertSame(1, $result);
    }

    public function testReadStatementReturnsFalseWhenExecutorFails(): void
    {
        $shadowStore = new ShadowStore();
        $schemaRegistry = new SchemaRegistry();
        $guard = new QueryGuard();
        $session = new ZteSession(
            $shadowStore,
            $schemaRegistry,
            $guard,
            new FixedRewriter(new RewritePlan('SELECT 1 AS id', QueryKind::READ)),
            new ShadowApplier($shadowStore),
            new ResultSelectRunner(),
            ZtdConfig::default()
        );
        $simulator = new StatementSimulator($session, ZtdConfig::default());

        $result = $simulator->simulate('SELECT 1 AS id', fn () => false);

        $this->assertFalse($result);
    }

    public function testWriteStatementUpdatesShadowStore(): void
    {
        $instance = Testcontainers::run(MySqlContainer::class);
        $port = $instance->getMappedPort(3306);
        if (!is_int($port)) {
            $this->fail('MySQL container port mapping missing for 3306.');
        }
        $host = str_replace('localhost', '127.0.0.1', $instance->getHost());

        $pdo = new PDO(
            "mysql:host=$host;port=$port;charset=utf8mb4",
            'root',
            'root',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $store = new ShadowStore();
        $schemaRegistry = new SchemaRegistry();
        $guard = new QueryGuard();
        $session = new ZteSession(
            $store,
            $schemaRegistry,
            $guard,
            new FixedRewriter(new RewritePlan('SELECT 2 AS id, \'Bob\' AS name', QueryKind::WRITE_SIMULATED, new InsertMutation('users'))),
            new ShadowApplier($store),
            new ResultSelectRunner(),
            ZtdConfig::default()
        );
        $simulator = new StatementSimulator($session, ZtdConfig::default());

        $result = $simulator->simulate('INSERT INTO users VALUES (2, \'Bob\')', fn (string $sql) => $pdo->query($sql));

        $this->assertSame(1, $result);
        $rows = $store->get('users');
        $this->assertCount(1, $rows);
        $this->assertSame('Bob', $rows[0]['name']);
        $id = $rows[0]['id'] ?? null;
        if (!is_int($id) && !is_string($id) && !is_float($id)) {
            $this->fail('Expected numeric id value.');
        }
        $this->assertSame(2, (int) $id);
    }

    public function testWriteStatementWithoutMutationThrows(): void
    {
        $shadowStore = new ShadowStore();
        $schemaRegistry = new SchemaRegistry();
        $guard = new QueryGuard();
        $session = new ZteSession(
            $shadowStore,
            $schemaRegistry,
            $guard,
            new FixedRewriter(new RewritePlan('SELECT 1 AS id', QueryKind::WRITE_SIMULATED)),
            new ShadowApplier($shadowStore),
            new ResultSelectRunner(),
            ZtdConfig::default()
        );
        $simulator = new StatementSimulator($session, ZtdConfig::default());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing shadow mutation');

        $simulator->simulate('UPDATE users SET name = \'Bob\' WHERE id = 1', fn () => false);
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
