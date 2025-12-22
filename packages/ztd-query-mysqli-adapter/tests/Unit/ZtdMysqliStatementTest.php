<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\StubMysqliStmt;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliStatement;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Session;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ZtdMysqliStatement::class)]
final class ZtdMysqliStatementTest extends TestCase
{
    public function testExecuteWithNullPlanDelegatesToDelegate(): void
    {
        $delegate = StubMysqliStmt::create();
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $stmt = new ZtdMysqliStatement($delegate, $session, null);

        self::assertTrue($stmt->execute());
        self::assertSame(1, $delegate->executeCallCount);
    }

    public function testExecuteWithNullPlanAndParamsDelegatesToDelegate(): void
    {
        $delegate = StubMysqliStmt::create();
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $stmt = new ZtdMysqliStatement($delegate, $session, null);

        self::assertTrue($stmt->execute([42]));
        self::assertSame([42], $delegate->executeCalledWithParams);
    }

    public function testExecuteReturnsFalseWhenShouldExecuteReturnsFalse(): void
    {
        $delegate = StubMysqliStmt::create();
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $plan = new RewritePlan('SELECT 1', QueryKind::SKIPPED);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        self::assertFalse($stmt->execute());
        self::assertSame(0, $delegate->executeCallCount);
    }

    public function testExecuteReadDelegatesToDelegate(): void
    {
        $delegate = StubMysqliStmt::create();
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $plan = new RewritePlan('SELECT * FROM users', QueryKind::READ);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        self::assertTrue($stmt->execute());
        self::assertSame(1, $delegate->executeCallCount);
    }

    public function testExecuteReadWithParamsDelegatesToDelegate(): void
    {
        $delegate = StubMysqliStmt::create();
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $plan = new RewritePlan('SELECT * FROM users WHERE id = ?', QueryKind::READ);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        self::assertTrue($stmt->execute([1]));
        self::assertSame([1], $delegate->executeCalledWithParams);
    }

    public function testExecuteWriteSimulatedReturnsFalseWhenDelegateFails(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->executeReturn = false;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $mutation = static::createStub(ShadowMutation::class);
        $plan = new RewritePlan('SELECT * FROM users', QueryKind::WRITE_SIMULATED, $mutation);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        self::assertFalse($stmt->execute());
    }

    public function testExecuteWriteSimulatedWithNoResultSet(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->getResultReturn = false;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $mutation = static::createStub(ShadowMutation::class);
        $plan = new RewritePlan('INSERT INTO users VALUES (1)', QueryKind::WRITE_SIMULATED, $mutation);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        self::assertTrue($stmt->execute());
    }

    public function testGetResultReturnsFalseForWriteWithoutResultSet(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->getResultReturn = false;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $mutation = static::createStub(ShadowMutation::class);
        $plan = new RewritePlan('INSERT', QueryKind::WRITE_SIMULATED, $mutation);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        $stmt->execute();

        self::assertFalse($stmt->get_result());
    }

    public function testZtdAffectedRowsReturnsFromResult(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->getResultReturn = false;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $mutation = static::createStub(ShadowMutation::class);
        $plan = new RewritePlan('INSERT', QueryKind::WRITE_SIMULATED, $mutation);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        $stmt->execute();

        self::assertSame(0, $stmt->ztdAffectedRows());
    }

    public function testNumRowsReturnsFromResult(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->getResultReturn = false;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $mutation = static::createStub(ShadowMutation::class);
        $plan = new RewritePlan('INSERT', QueryKind::WRITE_SIMULATED, $mutation);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        $stmt->execute();

        self::assertSame(0, $stmt->num_rows());
    }

    public function testNumRowsFallsBackToDelegate(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->numRowsReturn = 10;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $stmt = new ZtdMysqliStatement($delegate, $session, null);

        self::assertSame(10, $stmt->num_rows());
    }

    public function testFetchReturnsNullForWriteWithoutResultSet(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->getResultReturn = false;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $mutation = static::createStub(ShadowMutation::class);
        $plan = new RewritePlan('INSERT', QueryKind::WRITE_SIMULATED, $mutation);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        $stmt->execute();

        self::assertNull($stmt->fetch());
    }

    public function testCloseDelegatesToDelegate(): void
    {
        $delegate = StubMysqliStmt::create();
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $stmt = new ZtdMysqliStatement($delegate, $session, null);

        $stmt->close();

        self::assertTrue($delegate->closeCalled);
    }

    public function testResetClearsResultAndDelegates(): void
    {
        $delegate = StubMysqliStmt::create();
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $stmt = new ZtdMysqliStatement($delegate, $session, null);

        self::assertTrue($stmt->reset());
    }

    public function testExecuteReadWithoutParamsReturnsValue(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->executeReturn = false;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $plan = new RewritePlan('SELECT * FROM users', QueryKind::READ);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        self::assertFalse($stmt->execute());
    }

    public function testExecuteWriteSimulatedWithParamsSucceeds(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->getResultReturn = false;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $mutation = static::createStub(ShadowMutation::class);
        $plan = new RewritePlan('INSERT INTO users VALUES (?)', QueryKind::WRITE_SIMULATED, $mutation);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        self::assertTrue($stmt->execute([1]));
        self::assertSame([1], $delegate->executeCalledWithParams);
    }

    public function testExecuteWriteSimulatedWithParamsReturnsFalseOnFailure(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->executeReturn = false;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $mutation = static::createStub(ShadowMutation::class);
        $plan = new RewritePlan('INSERT INTO users VALUES (?)', QueryKind::WRITE_SIMULATED, $mutation);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        self::assertFalse($stmt->execute([1]));
    }

    public function testGetResultReturnsCachedResultAndClearsIt(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->getResultReturn = false;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $mutation = static::createStub(ShadowMutation::class);
        $plan = new RewritePlan('INSERT', QueryKind::WRITE_SIMULATED, $mutation);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        $stmt->execute();

        $first = $stmt->get_result();
        self::assertFalse($first);

        $second = $stmt->get_result();
        self::assertFalse($second);
    }

    public function testFetchDelegatesToDelegateWhenNoResult(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->fetchReturn = true;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $stmt = new ZtdMysqliStatement($delegate, $session, null);

        self::assertTrue($stmt->fetch());
    }

    public function testFetchDelegatesToDelegateForReadPlan(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->fetchReturn = true;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $plan = new RewritePlan('SELECT * FROM users', QueryKind::READ);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        $stmt->execute();

        self::assertTrue($stmt->fetch());
    }

    public function testNumRowsReturnsRowCountFromNonPassthroughResult(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->numRowsReturn = 99;
        $delegate->getResultReturn = false;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $mutation = static::createStub(ShadowMutation::class);
        $plan = new RewritePlan('INSERT', QueryKind::WRITE_SIMULATED, $mutation);
        $stmt = new ZtdMysqliStatement($delegate, $session, $plan);

        $stmt->execute();

        self::assertSame(0, $stmt->num_rows());
        self::assertNotSame(99, $stmt->num_rows());
    }

    public function testExecuteNullPlanWithoutParamsReturnsFalseOnFailure(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->executeReturn = false;
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class)
        );
        $stmt = new ZtdMysqliStatement($delegate, $session, null);

        self::assertFalse($stmt->execute());
    }
}
