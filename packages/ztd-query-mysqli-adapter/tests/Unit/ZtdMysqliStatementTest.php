<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\StubMysqliResult;
use Tests\Fixtures\StubMysqliStmt;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliStatementBindingBridge;
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
    public function testGet_resultAnswersTheResultTheStatementHolds(): void
    {
        $result = StubMysqliResult::create([['id' => 1]]);
        $delegate = StubMysqliStmt::create();
        $delegate->getResultReturn = $result;

        self::assertSame($result, $this->providerStatement($delegate)->get_result());
    }

    public function testFree_resultLetsGoOfTheResultTheStatementHeld(): void
    {
        $delegate = StubMysqliStmt::create();

        $this->providerStatement($delegate)->free_result();

        self::assertSame(['free_result'], $delegate->calls);
    }

    public function testStore_resultAsksTheStatementToReadItsResultInFull(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->storeResultReturn = true;

        self::assertTrue($this->providerStatement($delegate)->store_result());
    }

    public function testData_seekMovesTheStatementToTheRowItIsGiven(): void
    {
        $delegate = StubMysqliStmt::create();

        $this->providerStatement($delegate)->data_seek(2);

        self::assertSame(['data_seek:2'], $delegate->calls);
    }

    public function testResult_metadataAnswersWhatTheStatementSaysAboutItsColumns(): void
    {
        $metadata = StubMysqliResult::create();
        $delegate = StubMysqliStmt::create();
        $delegate->resultMetadataReturn = $metadata;

        self::assertSame($metadata, $this->providerStatement($delegate)->result_metadata());
    }

    public function testAttr_getAnswersWhatTheStatementSaysAnAttributeIsSetTo(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->attributeValue = 1;

        self::assertSame(1, $this->providerStatement($delegate)->attr_get(MYSQLI_STMT_ATTR_UPDATE_MAX_LENGTH));
    }

    public function testAttr_setSetsTheAttributeOnTheStatement(): void
    {
        $delegate = StubMysqliStmt::create();
        $statement = $this->providerStatement($delegate);

        $statement->attr_set(MYSQLI_STMT_ATTR_UPDATE_MAX_LENGTH, 1);

        self::assertSame(1, $statement->attr_get(MYSQLI_STMT_ATTR_UPDATE_MAX_LENGTH));
    }

    public function testGet_warningsAnswersFalseWhereTheStatementRaisedNone(): void
    {
        self::assertFalse($this->providerStatement(StubMysqliStmt::create())->get_warnings());
    }

    public function testMore_resultsAsksTheStatementWhetherAnotherResultFollows(): void
    {
        self::assertTrue($this->providerStatement(StubMysqliStmt::create())->more_results());
    }

    public function testNext_resultMovesTheStatementOnToItsNextResult(): void
    {
        $delegate = StubMysqliStmt::create();

        self::assertSame([true, ['next_result']], [$this->providerStatement($delegate)->next_result(), $delegate->calls]);
    }

    public function testNum_rowsAnswersWhatTheStatementSaysWhereZtdBufferedNothing(): void
    {
        $delegate = StubMysqliStmt::create();
        $delegate->numRowsReturn = 5;

        self::assertSame(5, $this->providerStatement($delegate)->num_rows());
    }

    public function testPreparePassesTheStatementOnToTheDriver(): void
    {
        $delegate = StubMysqliStmt::create();

        self::assertSame(
            [true, ['prepare:SELECT 1']],
            [$this->providerStatement($delegate)->prepare('SELECT 1'), $delegate->calls],
        );
    }

    public function testSend_long_dataPassesTheDataOnToTheStatement(): void
    {
        $delegate = StubMysqliStmt::create();

        self::assertSame(
            [true, ['send_long_data:0:blob']],
            [$this->providerStatement($delegate)->send_long_data(0, 'blob'), $delegate->calls],
        );
    }

    /**
     * @param StubMysqliStmt $delegate The statement ZTD is put in front of
     *
     * @return ZtdMysqliStatement The statement, with ZTD in front of it
     */
    public function providerStatement(StubMysqliStmt $delegate): ZtdMysqliStatement
    {
        $session = new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class),
        );

        return new ZtdMysqliStatement($delegate, $session, null);
    }
}
