<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\StubMysqliStmt;
use ZtdQuery\Adapter\Mysqli\MysqliStatementBindingBridge;
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
#[UsesClass(MysqliStatementBindingBridge::class)]
final class ZtdMysqliStatementTest extends TestCase
{
    public function testBindingBridgeUsesTheStatementDelegateInitializedByTheConstructor(): void
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
        $value = null;

        self::assertTrue($stmt->bind_result($value));
    }

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

    public function testGet_resultReturnsFalseForWriteWithoutResultSet(): void
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

    public function testGet_resultReturnsCachedResultAndClearsIt(): void
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

    public function testFree_resultPreservesNativeArgumentsAndResult(): void
    {
        $native = StubMysqliStmt::create();
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class));
        $ztd = new ZtdMysqliStatement($native, $session, null);

        $ztd->free_result();
        self::assertSame([['free_result', []]], $native->calls);
    }

    public function testStore_resultPreservesNativeArgumentsAndResult(): void
    {
        $native = StubMysqliStmt::create();
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class));
        $ztd = new ZtdMysqliStatement($native, $session, null);

        self::assertSame(true, $ztd->store_result());
        self::assertSame([['store_result', []]], $native->calls);
    }

    public function testData_seekPreservesNativeArgumentsAndResult(): void
    {
        $native = StubMysqliStmt::create();
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class));
        $ztd = new ZtdMysqliStatement($native, $session, null);

        $ztd->data_seek(3);
        self::assertSame([['data_seek', [3]]], $native->calls);
    }

    public function testResult_metadataPreservesNativeArgumentsAndResult(): void
    {
        $native = StubMysqliStmt::create();
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class));
        $ztd = new ZtdMysqliStatement($native, $session, null);

        self::assertSame(false, $ztd->result_metadata());
        self::assertSame([['result_metadata', []]], $native->calls);
    }

    public function testAttr_getPreservesNativeArgumentsAndResult(): void
    {
        $native = StubMysqliStmt::create();
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class));
        $ztd = new ZtdMysqliStatement($native, $session, null);

        self::assertSame(0, $ztd->attr_get(MYSQLI_STMT_ATTR_CURSOR_TYPE));
        self::assertSame([['attr_get', [MYSQLI_STMT_ATTR_CURSOR_TYPE]]], $native->calls);
    }

    public function testAttr_setPreservesNativeArgumentsAndResult(): void
    {
        $native = StubMysqliStmt::create();
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class));
        $ztd = new ZtdMysqliStatement($native, $session, null);

        self::assertSame(true, $ztd->attr_set(MYSQLI_STMT_ATTR_CURSOR_TYPE, MYSQLI_CURSOR_TYPE_READ_ONLY));
        self::assertSame([['attr_set', [MYSQLI_STMT_ATTR_CURSOR_TYPE, MYSQLI_CURSOR_TYPE_READ_ONLY]]], $native->calls);
    }

    public function testGet_warningsPreservesNativeArgumentsAndResult(): void
    {
        $native = StubMysqliStmt::create();
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class));
        $ztd = new ZtdMysqliStatement($native, $session, null);

        self::assertSame(false, $ztd->get_warnings());
        self::assertSame([['get_warnings', []]], $native->calls);
    }

    public function testMore_resultsPreservesNativeArgumentsAndResult(): void
    {
        $native = StubMysqliStmt::create();
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class));
        $ztd = new ZtdMysqliStatement($native, $session, null);

        self::assertSame(false, $ztd->more_results());
        self::assertSame([['more_results', []]], $native->calls);
    }

    public function testNext_resultPreservesNativeArgumentsAndResult(): void
    {
        $native = StubMysqliStmt::create();
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class));
        $ztd = new ZtdMysqliStatement($native, $session, null);

        self::assertSame(false, $ztd->next_result());
        self::assertSame([['next_result', []]], $native->calls);
    }

    public function testNum_rowsPreservesNativeArgumentsAndResult(): void
    {
        $native = StubMysqliStmt::create();
        $native->numRowsReturn = 5;
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class));
        $ztd = new ZtdMysqliStatement($native, $session, null);

        self::assertSame(5, $ztd->num_rows());
        self::assertSame([['num_rows', []]], $native->calls);
    }

    public function testPreparePreservesNativeArgumentsAndResult(): void
    {
        $native = StubMysqliStmt::create();
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class));
        $ztd = new ZtdMysqliStatement($native, $session, null);

        self::assertSame(true, $ztd->prepare('SELECT 42'));
        self::assertSame([['prepare', ['SELECT 42']]], $native->calls);
    }

    public function testSend_long_dataPreservesNativeArgumentsAndResult(): void
    {
        $native = StubMysqliStmt::create();
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), self::createStub(ConnectionInterface::class));
        $ztd = new ZtdMysqliStatement($native, $session, null);

        self::assertSame(true, $ztd->send_long_data(2, 'payload'));
        self::assertSame([['send_long_data', [2, 'payload']]], $native->calls);
    }
}
