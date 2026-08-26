<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Tests\Fixtures\AnsweringPdoStatement;
use ZtdQuery\Adapter\Pdo\BufferedRow;
use ZtdQuery\Adapter\Pdo\PdoBinding;
use ZtdQuery\Adapter\Pdo\PdoFetchMode;
use ZtdQuery\Adapter\Pdo\PdoParameterBinder;
use ZtdQuery\Adapter\Pdo\PdoParameterKind;
use ZtdQuery\Adapter\Pdo\PdoPreparedExecution;
use ZtdQuery\Adapter\Pdo\PdoStatement;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Adapter\Pdo\ZtdPdoStatement;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Exception\MissingPrimaryKeyException;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Session;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ZtdPdoStatement::class)]
#[UsesClass(BufferedRow::class)]
#[UsesClass(PdoBinding::class)]
#[UsesClass(PdoFetchMode::class)]
#[UsesClass(PdoStatement::class)]
#[UsesClass(PdoParameterBinder::class)]
#[UsesClass(PdoParameterKind::class)]
#[UsesClass(PdoPreparedExecution::class)]
#[UsesClass(ZtdPdoException::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\DriverSessionFactory::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\PdoConnection::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\PostgreSqlCopy::class)]
#[UsesClass(ZtdPdo::class)]
final class ZtdPdoStatementTest extends TestCase
{
    public function testExecuteDelegatesWhenNoPlan(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $inner = $pdo->prepare('INSERT INTO t VALUES (1)');
        self::assertNotFalse($inner);

        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        $stmt = new ZtdPdoStatement($inner, $session, null);
        self::assertTrue($stmt->execute());
    }

    public function testExecuteReturnsFalseWhenShouldExecuteIsFalse(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $inner = $pdo->prepare('SELECT * FROM t');
        self::assertNotFalse($inner);

        $plan = new RewritePlan('SELECT 1', QueryKind::SKIPPED);

        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));
        $stmt = new ZtdPdoStatement($inner, $session, $plan);
        self::assertFalse($stmt->execute());
    }

    public function testExecuteDelegatesWhenNoPostProcessingNeeded(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $inner = $pdo->prepare('SELECT * FROM t');
        self::assertNotFalse($inner);

        $plan = new RewritePlan('SELECT * FROM t', QueryKind::READ);

        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));
        $stmt = new ZtdPdoStatement($inner, $session, $plan);
        self::assertTrue($stmt->execute());
    }

    public function testExecuteWithoutPostProcessingRunsNativeStatementOnce(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $inner = $pdo->prepare('INSERT INTO t VALUES (1)');
        self::assertNotFalse($inner);

        $plan = new RewritePlan('INSERT INTO t VALUES (1)', QueryKind::READ);
        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));
        $stmt = new ZtdPdoStatement($inner, $session, $plan);

        self::assertTrue($stmt->execute());
        $count = $pdo->query('SELECT COUNT(*) FROM t');
        self::assertNotFalse($count);
        self::assertSame(1, $count->fetchColumn());
    }

    public function testBindValueSurvivesPreparedStatementRecompilation(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $rewriter = static::createStub(SqlRewriter::class);
        $rewriter->method('rewrite')->willReturn(new RewritePlan('SELECT ? AS value', QueryKind::READ));
        $session = new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));
        $execution = new PdoPreparedExecution($pdo, $session, 'SELECT ? AS value', []);
        $prepared = $execution->prepare(null);
        $stmt = new ZtdPdoStatement($prepared['statement'], $session, $prepared['plan'], $execution);

        self::assertTrue($stmt->bindValue(1, 42, PDO::PARAM_INT));
        self::assertTrue($stmt->execute());
        self::assertSame(42, $stmt->fetchColumn());
    }

    public function testBindParamSurvivesPreparedStatementRecompilation(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $rewriter = static::createStub(SqlRewriter::class);
        $rewriter->method('rewrite')->willReturn(new RewritePlan('SELECT ? AS value', QueryKind::READ));
        $session = new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));
        $execution = new PdoPreparedExecution($pdo, $session, 'SELECT ? AS value', []);
        $prepared = $execution->prepare(null);
        $stmt = new ZtdPdoStatement($prepared['statement'], $session, $prepared['plan'], $execution);
        $value = 41;

        self::assertTrue($stmt->bindParam(1, $value, PDO::PARAM_INT));
        $value = 43;
        self::assertTrue($stmt->execute());
        self::assertSame(43, $stmt->fetchColumn());
    }

    public function testExecuteWrapsSimulationFailureAsAdapterException(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $inner = $pdo->prepare("SELECT 1 AS id, 'Bob' AS name");
        self::assertNotFalse($inner);

        $shadowStore = new ShadowStore();
        $shadowStore->set('users', [['id' => 1, 'name' => 'Alice']]);
        $typeResolver = static::createStub(ResultColumnTypeResolver::class);
        $typeResolver->method('resolve')->willReturn(new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'));
        $session = new Session(
            static::createStub(SqlRewriter::class),
            $shadowStore,
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class),
            resultColumnTypeResolver: $typeResolver,
        );
        $plan = new RewritePlan(
            "SELECT 1 AS id, 'Bob' AS name",
            QueryKind::WRITE_SIMULATED,
            new UpdateMutation('users', []),
        );
        $stmt = new ZtdPdoStatement($inner, $session, $plan);

        try {
            $stmt->execute();
            self::fail('Expected a ZTD PDO exception.');
        } catch (ZtdPdoException $exception) {
            self::assertSame(0, $exception->getCode());
            $databaseException = $exception->getPrevious();
            self::assertInstanceOf(DatabaseException::class, $databaseException);
            self::assertInstanceOf(MissingPrimaryKeyException::class, $databaseException->getPrevious());
        }
    }

    public function testBindValueDelegatesToInner(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER, name TEXT)');
        $inner = $pdo->prepare('INSERT INTO t VALUES (:id, :name)');
        self::assertNotFalse($inner);

        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        $stmt = new ZtdPdoStatement($inner, $session, null);
        self::assertTrue($stmt->bindValue(1, 'test'));
    }

    public function testRowCountDelegatesToInnerWhenNoResult(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $pdo->exec('INSERT INTO t VALUES (1)');
        $pdo->exec('INSERT INTO t VALUES (2)');

        $inner = $pdo->prepare('SELECT * FROM t');
        self::assertNotFalse($inner);
        $inner->execute();

        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        $stmt = new ZtdPdoStatement($inner, $session, null);
        self::assertSame(0, $stmt->rowCount());
    }

    public function testCloseCursorDelegatesToInner(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $inner = $pdo->prepare('SELECT * FROM t');
        self::assertNotFalse($inner);
        $inner->execute();

        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        $stmt = new ZtdPdoStatement($inner, $session, null);
        self::assertTrue($stmt->closeCursor());
    }

    public function testColumnCountDelegatesToInner(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER, name TEXT, value REAL)');
        $inner = $pdo->prepare('SELECT * FROM t');
        self::assertNotFalse($inner);
        $inner->execute();

        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        $stmt = new ZtdPdoStatement($inner, $session, null);
        self::assertSame(3, $stmt->columnCount());
    }
    public function testBindColumnFillsTheVariableTheColumnIsReadInto(): void
    {
        $statement = $this->providerPlainStatement('SELECT 7 AS id');
        $id = null;

        $statement->bindColumn('id', $id);
        $statement->execute();
        $statement->fetch(PDO::FETCH_BOUND);

        self::assertSame('7', $id);
    }

    public function testExecuteStatementRunsWhatTheDriverPrepared(): void
    {
        $statement = $this->providerPlainStatement('SELECT 1 AS id');

        self::assertTrue($statement->executeStatement(null));
    }

    public function testExecuteStatementBindsTheParametersItIsGiven(): void
    {
        $statement = $this->providerRewrittenStatement('SELECT ? AS value');

        $statement->executeStatement([42]);

        self::assertSame(42, $statement->fetchColumn());
    }

    public function testExecuteAndPostProcessLetsZtdReadWhatTheStatementAnswered(): void
    {
        $statement = $this->providerReturningInsert();

        self::assertSame([['id' => 1, 'name' => 'linus']], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testRebindParametersPutsEveryBindingBackOnTheStatement(): void
    {
        $statement = $this->providerRewrittenStatement('SELECT ? AS value');
        $statement->bindValue(1, 42, PDO::PARAM_INT);

        $statement->rebindParameters();
        $statement->executeStatement(null);

        self::assertSame(42, $statement->fetchColumn());
    }

    public function testFetchAnswersOneBufferedRowAtATime(): void
    {
        $statement = $this->providerReturningInsert();

        self::assertSame(['id' => 1, 'name' => 'linus'], $statement->fetch(PDO::FETCH_ASSOC));
    }

    public function testFetchAnswersFalseOnceTheBufferedRowsRunOut(): void
    {
        $statement = $this->providerReturningInsert();
        $statement->fetch(PDO::FETCH_ASSOC);

        self::assertFalse($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function testFetchAllAnswersEveryBufferedRowInTheModeItIsAsked(): void
    {
        $statement = $this->providerReturningInsert();

        self::assertSame([[1, 'linus']], $statement->fetchAll(PDO::FETCH_NUM));
    }

    public function testFetchAllAnswersOneColumnWhereOnlyOneIsAsked(): void
    {
        $statement = $this->providerReturningInsert();

        self::assertSame(['linus'], $statement->fetchAll(PDO::FETCH_COLUMN, 1));
    }

    public function testFetchColumnAnswersTheColumnAskedForInTheBufferedRow(): void
    {
        $statement = $this->providerReturningInsert();

        self::assertSame('linus', $statement->fetchColumn(1));
    }

    /**
     * @throws ReflectionException
     */
    public function testFetchObjectBuildsAnObjectOutOfTheBufferedRow(): void
    {
        $statement = $this->providerReturningInsert();

        $object = $statement->fetchObject();

        self::assertSame(['id' => 1, 'name' => 'linus'], $object === false ? [] : get_object_vars($object));
    }

    /**
     * @throws ReflectionException
     */
    public function testFetchObjectAnswersFalseWhereThereIsNoBufferedRowLeft(): void
    {
        $statement = $this->providerReturningInsert();
        $statement->fetchObject();

        self::assertFalse($statement->fetchObject());
    }

    public function testSetFetchModeIsRememberedAcrossAReprepare(): void
    {
        $statement = $this->providerRewrittenStatement('SELECT ? AS value');
        $statement->setFetchMode(PDO::FETCH_NUM);

        $statement->execute([42]);

        self::assertSame([[42]], $statement->fetchAll());
    }

    public function testResolveFetchModeAnswersTheModeItWasAsked(): void
    {
        $statement = $this->providerPlainStatement('SELECT 1 AS id');

        self::assertSame(PDO::FETCH_ASSOC, $statement->resolveFetchMode(PDO::FETCH_ASSOC));
    }

    public function testResolveFetchModeAnswersTheModeLastSetWhereNoneIsAsked(): void
    {
        $statement = $this->providerPlainStatement('SELECT 1 AS id');
        $statement->setFetchMode(PDO::FETCH_NUM);

        self::assertSame(PDO::FETCH_NUM, $statement->resolveFetchMode(PDO::FETCH_DEFAULT));
    }

    public function testResolveFetchModeFallsBackToTheConnectionsOwnMode(): void
    {
        $statement = $this->providerPlainStatement('SELECT 1 AS id');

        self::assertSame(PDO::FETCH_BOTH, $statement->resolveFetchMode(PDO::FETCH_DEFAULT));
    }

    public function testErrorCodeAnswersWhatTheDriverSaysWentWrongLast(): void
    {
        self::assertSame('00000', $this->providerStubbedStatement()->errorCode());
    }

    public function testErrorCodeAnswersNothingWhereTheDriverSaysNothing(): void
    {
        self::assertSame('', $this->providerPlainStatement('SELECT 1 AS id')->errorCode());
    }

    public function testErrorInfoAnswersWhatTheDriverSaysAboutTheLastFailure(): void
    {
        self::assertSame('00000', $this->providerStubbedStatement()->errorInfo()[0]);
    }

    public function testGetAttributeReadsTheAttributeOffTheStatementItWraps(): void
    {
        self::assertSame(PDO::CURSOR_FWDONLY, $this->providerStubbedStatement()->getAttribute(PDO::ATTR_CURSOR));
    }

    public function testSetAttributeSetsTheAttributeOnTheStatementItWraps(): void
    {
        self::assertTrue($this->providerStubbedStatement()->setAttribute(PDO::ATTR_CURSOR, PDO::CURSOR_FWDONLY));
    }

    public function testGetColumnMetaAnswersWhatTheDriverSaysAboutAColumn(): void
    {
        $meta = $this->providerStubbedStatement()->getColumnMeta(0);

        self::assertSame('id', $meta === false ? null : $meta['name']);
    }

    public function testNextRowsetMovesTheStatementOnToTheDriversNextResult(): void
    {
        self::assertTrue($this->providerStubbedStatement()->nextRowset());
    }

    public function testDebugDumpParamsWritesTheDumpRatherThanAnsweringIt(): void
    {
        $statement = $this->providerPlainStatement('SELECT 1 AS id');

        ob_start();
        $dumped = $statement->debugDumpParams();
        ob_end_clean();

        self::assertTrue($dumped);
    }

    public function testGetIteratorWalksTheRowsZtdBuffered(): void
    {
        $statement = $this->providerReturningInsert();

        self::assertSame([['id' => 1, 0 => 1, 'name' => 'linus', 1 => 'linus']], iterator_to_array($statement->getIterator()));
    }

    public function testGetIteratorWalksTheDriversOwnCursorWhereNothingWasBuffered(): void
    {
        $statement = $this->providerPlainStatement('SELECT 1 AS id');
        $statement->execute();

        self::assertCount(1, iterator_to_array($statement->getIterator()));
    }

    /**
     * @return ZtdPdoStatement A statement over a driver that answers whatever is asked of it
     */
    public function providerStubbedStatement(): ZtdPdoStatement
    {
        $inner = new AnsweringPdoStatement();
        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        return new ZtdPdoStatement($inner, $session, null);
    }

    /**
     * @param string $sql Statement to prepare
     *
     * @return ZtdPdoStatement A statement ZTD passes straight through to the driver
     */
    public function providerPlainStatement(string $sql): ZtdPdoStatement
    {
        $pdo = new PDO('sqlite::memory:');
        $inner = $pdo->prepare($sql);
        self::assertNotFalse($inner);
        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        return new ZtdPdoStatement($inner, $session, null);
    }

    /**
     * @param string $sql Statement to prepare, which ZTD rewrites into itself
     *
     * @return ZtdPdoStatement A statement that is prepared again on each execution
     */
    public function providerRewrittenStatement(string $sql): ZtdPdoStatement
    {
        $pdo = new PDO('sqlite::memory:');
        $rewriter = static::createStub(SqlRewriter::class);
        $rewriter->method('rewrite')->willReturn(new RewritePlan($sql, QueryKind::READ));
        $session = new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));
        $execution = new PdoPreparedExecution($pdo, $session, $sql, []);
        $prepared = $execution->prepare(null);

        return new ZtdPdoStatement($prepared['statement'], $session, $prepared['plan'], $execution);
    }

    /**
     * @return ZtdPdoStatement An executed INSERT ... RETURNING, holding the row ZTD buffered for it
     */
    public function providerReturningInsert(): ZtdPdoStatement
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $pdo->exec("INSERT INTO users (name) VALUES ('ada')");
        $statement = ZtdPdo::fromPdo($pdo)->prepare('INSERT INTO users (name) VALUES (?) RETURNING id, name');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->execute(['linus']);

        return $statement;
    }
}
