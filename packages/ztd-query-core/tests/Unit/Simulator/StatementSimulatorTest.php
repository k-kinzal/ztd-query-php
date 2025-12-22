<?php

declare(strict_types=1);

namespace Tests\Unit\Simulator;

use Tests\Fake\ExceptionThrowingRewriter;
use Tests\Fake\FixedRewriter;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Simulator\StatementSimulator;
use ZtdQuery\Session;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(ZtdConfig::class)]
#[UsesClass(DatabaseException::class)]
#[UsesClass(UnsupportedSqlException::class)]
#[UsesClass(RewritePlan::class)]
#[UsesClass(ResultSelectRunner::class)]
#[UsesClass(InsertMutation::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(Session::class)]
#[CoversClass(StatementSimulator::class)]
final class StatementSimulatorTest extends TestCase
{
    public function testUnsupportedSqlThrows(): void
    {
        $shadowStore = new ShadowStore();
        $connection = static::createStub(ConnectionInterface::class);
        $session = new Session(
            new ExceptionThrowingRewriter(new UnsupportedSqlException('DROP TABLE users', 'Unsupported')),
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            $connection
        );
        $simulator = new StatementSimulator($session);

        $this->expectException(DatabaseException::class);

        $simulator->simulate('DROP TABLE users', fn () => false);
    }

    public function testReadStatementReturnsRowCount(): void
    {
        $shadowStore = new ShadowStore();
        $connection = static::createStub(ConnectionInterface::class);
        $session = new Session(
            new FixedRewriter(new RewritePlan('SELECT 1 AS id', QueryKind::READ)),
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            $connection
        );
        $simulator = new StatementSimulator($session);

        $statement = static::createStub(StatementInterface::class);
        $statement->method('rowCount')->willReturn(1);

        $result = $simulator->simulate('SELECT 1 AS id', function (string $sql) use ($statement) {
            return $statement;
        });

        self::assertSame(1, $result);
    }

    public function testReadStatementReturnsFalseWhenExecutorFails(): void
    {
        $shadowStore = new ShadowStore();
        $connection = static::createStub(ConnectionInterface::class);
        $session = new Session(
            new FixedRewriter(new RewritePlan('SELECT 1 AS id', QueryKind::READ)),
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            $connection
        );
        $simulator = new StatementSimulator($session);

        $result = $simulator->simulate('SELECT 1 AS id', fn () => false);

        self::assertFalse($result);
    }

    public function testWriteStatementUpdatesShadowStore(): void
    {
        $store = new ShadowStore();
        $connection = static::createStub(ConnectionInterface::class);

        $resultStatement = static::createStub(StatementInterface::class);
        $resultStatement->method('fetchAll')->willReturn([
            ['id' => 2, 'name' => 'Bob'],
        ]);
        $connection->method('query')->willReturn($resultStatement);

        $session = new Session(
            new FixedRewriter(new RewritePlan('SELECT 2 AS id, \'Bob\' AS name', QueryKind::WRITE_SIMULATED, new InsertMutation('users'))),
            $store,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            $connection
        );
        $simulator = new StatementSimulator($session);

        $executorStatement = static::createStub(StatementInterface::class);
        $executorStatement->method('fetchAll')->willReturn([
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $result = $simulator->simulate('INSERT INTO users VALUES (2, \'Bob\')', function (string $sql) use ($executorStatement) {
            return $executorStatement;
        });

        self::assertSame(1, $result);
        $rows = $store->get('users');
        self::assertCount(1, $rows);
        self::assertSame('Bob', $rows[0]['name']);
        self::assertSame(2, $rows[0]['id']);
    }

    public function testWriteStatementWithoutMutationThrows(): void
    {
        $shadowStore = new ShadowStore();
        $connection = static::createStub(ConnectionInterface::class);
        $session = new Session(
            new FixedRewriter(new RewritePlan('SELECT 1 AS id', QueryKind::WRITE_SIMULATED)),
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            $connection
        );
        $simulator = new StatementSimulator($session);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing shadow mutation');

        $simulator->simulate('UPDATE users SET name = \'Bob\' WHERE id = 1', fn () => false);
    }
}
