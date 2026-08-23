<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\PdoStatement;
use ZtdQuery\Adapter\Pdo\PdoParameterBinder;
use ZtdQuery\Adapter\Pdo\PdoParameterType;
use ZtdQuery\Adapter\Pdo\PdoPreparedExecution;
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
use ZtdQuery\Session;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ZtdPdoStatement::class)]
#[UsesClass(PdoStatement::class)]
#[UsesClass(PdoParameterBinder::class)]
#[UsesClass(PdoParameterType::class)]
#[UsesClass(PdoPreparedExecution::class)]
#[UsesClass(ZtdPdoException::class)]
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
        self::assertInstanceOf(\PDOStatement::class, $count);
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
        $typeResolver->method('resolve')->willReturn(new ColumnType(ColumnTypeFamily::INTEGER, 'INTEGER'));
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
}
