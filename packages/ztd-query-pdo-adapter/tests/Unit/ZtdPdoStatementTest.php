<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ZtdQuery\Adapter\Pdo\Driver\PdoStatement;
use ZtdQuery\Adapter\Pdo\Session\BufferedRow;
use ZtdQuery\Adapter\Pdo\Session\ParameterBinder;
use ZtdQuery\Adapter\Pdo\Session\ParameterKind;
use ZtdQuery\Adapter\Pdo\Session\PreparedQuery;
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
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Session;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ZtdPdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ZtdPdoException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ZtdPdo::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Driver\PdoConnection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(PdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\StatementExecution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\Bindings::class)]
#[CoversClass(BufferedRow::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\DriverSessionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ParameterKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ParameterBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(PreparedQuery::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ConnectionExecution::class)]
final class ZtdPdoStatementTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testBufferedObjectHydrationUsesDeclaredProperties(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE errors (id INTEGER PRIMARY KEY, message TEXT)');
        $statement = ZtdPdo::fromPdo($pdo)->prepare("INSERT INTO errors VALUES (1, 'row message') RETURNING message");
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        self::assertTrue($statement->execute());
        $object = $statement->fetchObject(PDOException::class, ['constructor message', 7]);
        self::assertInstanceOf(PDOException::class, $object);
        self::assertSame('row message', $object->getMessage());
        self::assertSame(7, $object->getCode());
    }

    public function testPassthroughFetchAllForwardsConstructorArguments(): void
    {
        $pdo = ZtdPdo::fromPdo(new PDO('sqlite::memory:'));
        $statement = $pdo->prepare("SELECT 'row message' AS message");
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        self::assertTrue($statement->execute());
        $objects = $statement->fetchAll(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, PDOException::class, ['constructor message', 7]);
        self::assertCount(1, $objects);
        self::assertInstanceOf(PDOException::class, $objects[0]);
        self::assertSame('row message', $objects[0]->getMessage());
        self::assertSame(7, $objects[0]->getCode());
    }

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
        $stmt = ZtdPdo::fromPdo($pdo)->prepare('SELECT ? AS value');
        self::assertInstanceOf(ZtdPdoStatement::class, $stmt);

        self::assertTrue($stmt->bindValue(1, 42, PDO::PARAM_INT));
        self::assertTrue($stmt->execute());
        self::assertSame(42, $stmt->fetchColumn());
    }

    public function testBindParamSurvivesPreparedStatementRecompilation(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $stmt = ZtdPdo::fromPdo($pdo)->prepare('SELECT ? AS value');
        self::assertInstanceOf(ZtdPdoStatement::class, $stmt);

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
    public function testBindColumnFillsTheVariableTheColumnIsReadInto(): void
    {
        $native = new PDO('sqlite::memory:');
        $inner = $native->prepare('SELECT 7 AS id');
        self::assertNotFalse($inner);
        $session = (new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory())->create(new \ZtdQuery\Adapter\Pdo\Driver\PdoConnection($native), ZtdConfig::default());
        $statement = new ZtdPdoStatement($inner, $session, null);
        $id = null;

        $statement->bindColumn('id', $id);
        $statement->execute();
        $statement->fetch(PDO::FETCH_BOUND);

        self::assertSame('7', $id);
    }





    public function testExecuteAndPostProcessLetsZtdReadWhatTheStatementAnswered(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $statement = ZtdPdo::fromPdo($native)->prepare('INSERT INTO users (name) VALUES (?) RETURNING id, name');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->execute(['linus']);

        self::assertSame([['id' => 1, 'name' => 'linus']], $statement->fetchAll(PDO::FETCH_ASSOC));
    }



    public function testFetchAnswersOneBufferedRowAtATime(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $statement = ZtdPdo::fromPdo($native)->prepare('INSERT INTO users (name) VALUES (?) RETURNING id, name');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->execute(['linus']);

        self::assertSame(['id' => 1, 'name' => 'linus'], $statement->fetch(PDO::FETCH_ASSOC));
    }

    public function testFetchAnswersFalseOnceTheBufferedRowsRunOut(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $statement = ZtdPdo::fromPdo($native)->prepare('INSERT INTO users (name) VALUES (?) RETURNING id, name');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->execute(['linus']);
        $statement->fetch(PDO::FETCH_ASSOC);

        self::assertFalse($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function testFetchAllAnswersEveryBufferedRowInTheModeItIsAsked(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $statement = ZtdPdo::fromPdo($native)->prepare('INSERT INTO users (name) VALUES (?) RETURNING id, name');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->execute(['linus']);

        self::assertSame([[1, 'linus']], $statement->fetchAll(PDO::FETCH_NUM));
    }

    public function testFetchAllAnswersOneColumnWhereOnlyOneIsAsked(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $statement = ZtdPdo::fromPdo($native)->prepare('INSERT INTO users (name) VALUES (?) RETURNING id, name');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->execute(['linus']);

        self::assertSame(['linus'], $statement->fetchAll(PDO::FETCH_COLUMN, 1));
    }

    public function testFetchColumnAnswersTheColumnAskedForInTheBufferedRow(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $statement = ZtdPdo::fromPdo($native)->prepare('INSERT INTO users (name) VALUES (?) RETURNING id, name');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->execute(['linus']);

        self::assertSame('linus', $statement->fetchColumn(1));
    }

    /**
     * @throws ReflectionException
     */
    public function testFetchObjectBuildsAnObjectOutOfTheBufferedRow(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $statement = ZtdPdo::fromPdo($native)->prepare('INSERT INTO users (name) VALUES (?) RETURNING id, name');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->execute(['linus']);

        $object = $statement->fetchObject();

        self::assertSame(['id' => 1, 'name' => 'linus'], $object === false ? [] : get_object_vars($object));
    }

    /**
     * @throws ReflectionException
     */
    public function testFetchObjectAnswersFalseWhereThereIsNoBufferedRowLeft(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $statement = ZtdPdo::fromPdo($native)->prepare('INSERT INTO users (name) VALUES (?) RETURNING id, name');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->execute(['linus']);
        $statement->fetchObject();

        self::assertFalse($statement->fetchObject());
    }

    public function testSetFetchModeIsRememberedAcrossAReprepare(): void
    {
        $native = new PDO('sqlite::memory:');
        $statement = ZtdPdo::fromPdo($native)->prepare('SELECT ? AS value');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->setFetchMode(PDO::FETCH_NUM);

        $statement->execute([42]);

        self::assertSame([[42]], $statement->fetchAll());
    }







    public function testErrorCodeAnswersWhatTheDriverSaysWentWrongLast(): void
    {
        $native = new PDO('sqlite::memory:');
        $inner = $native->prepare('SELECT 1 AS id');
        self::assertNotFalse($inner);
        $session = (new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory())->create(new \ZtdQuery\Adapter\Pdo\Driver\PdoConnection($native), ZtdConfig::default());
        $statement = new ZtdPdoStatement($inner, $session, null);
        $statement->execute();
        self::assertSame('00000', $statement->errorCode());
    }

    public function testErrorCodeAnswersNothingWhereTheDriverSaysNothing(): void
    {
        $native = new PDO('sqlite::memory:');
        $inner = $native->prepare('SELECT 1 AS id');
        self::assertNotFalse($inner);
        $session = (new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory())->create(new \ZtdQuery\Adapter\Pdo\Driver\PdoConnection($native), ZtdConfig::default());
        $statement = new ZtdPdoStatement($inner, $session, null);
        self::assertSame('', $statement->errorCode());
    }

    public function testErrorInfoAnswersWhatTheDriverSaysAboutTheLastFailure(): void
    {
        $native = new PDO('sqlite::memory:');
        $inner = $native->prepare('SELECT 1 AS id');
        self::assertNotFalse($inner);
        $session = (new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory())->create(new \ZtdQuery\Adapter\Pdo\Driver\PdoConnection($native), ZtdConfig::default());
        $statement = new ZtdPdoStatement($inner, $session, null);
        $statement->execute();
        self::assertSame('00000', $statement->errorInfo()[0]);
    }

    public function testGetAttributeReadsTheAttributeOffTheStatementItWraps(): void
    {
        $native = new PDO('sqlite::memory:');
        $inner = $native->prepare('SELECT 1 AS id');
        self::assertNotFalse($inner);
        $session = (new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory())->create(new \ZtdQuery\Adapter\Pdo\Driver\PdoConnection($native), ZtdConfig::default());
        $statement = new ZtdPdoStatement($inner, $session, null);
        $statement->execute();
        $this->expectException(PDOException::class);
        $statement->getAttribute(PDO::ATTR_CURSOR);
    }

    public function testSetAttributeSetsTheAttributeOnTheStatementItWraps(): void
    {
        $native = new PDO('sqlite::memory:');
        $inner = $native->prepare('SELECT 1 AS id');
        self::assertNotFalse($inner);
        $session = (new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory())->create(new \ZtdQuery\Adapter\Pdo\Driver\PdoConnection($native), ZtdConfig::default());
        $statement = new ZtdPdoStatement($inner, $session, null);
        $statement->execute();
        try {
            $expected = $inner->setAttribute(PDO::ATTR_CURSOR, PDO::CURSOR_FWDONLY);
        } catch (PDOException $failure) {
            $this->expectExceptionObject($failure);
            $statement->setAttribute(PDO::ATTR_CURSOR, PDO::CURSOR_FWDONLY);
            return;
        }
        self::assertSame($expected, $statement->setAttribute(PDO::ATTR_CURSOR, PDO::CURSOR_FWDONLY));
    }

    public function testGetColumnMetaAnswersWhatTheDriverSaysAboutAColumn(): void
    {
        $native = new PDO('sqlite::memory:');
        $inner = $native->prepare('SELECT 1 AS id');
        self::assertNotFalse($inner);
        $session = (new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory())->create(new \ZtdQuery\Adapter\Pdo\Driver\PdoConnection($native), ZtdConfig::default());
        $statement = new ZtdPdoStatement($inner, $session, null);
        $statement->execute();
        $meta = $statement->getColumnMeta(0);

        self::assertSame('id', $meta === false ? null : $meta['name']);
    }

    public function testNextRowsetMovesTheStatementOnToTheDriversNextResult(): void
    {
        $native = new PDO('sqlite::memory:');
        $inner = $native->prepare('SELECT 1 AS id');
        self::assertNotFalse($inner);
        $session = (new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory())->create(new \ZtdQuery\Adapter\Pdo\Driver\PdoConnection($native), ZtdConfig::default());
        $statement = new ZtdPdoStatement($inner, $session, null);
        $statement->execute();
        $this->expectException(PDOException::class);
        $statement->nextRowset();
    }

    public function testDebugDumpParamsWritesTheDumpRatherThanAnsweringIt(): void
    {
        $native = new PDO('sqlite::memory:');
        $inner = $native->prepare('SELECT 1 AS id');
        self::assertNotFalse($inner);
        $session = (new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory())->create(new \ZtdQuery\Adapter\Pdo\Driver\PdoConnection($native), ZtdConfig::default());
        $statement = new ZtdPdoStatement($inner, $session, null);

        ob_start();
        $dumped = $statement->debugDumpParams();
        $output = ob_get_clean();

        self::assertTrue($dumped);
        self::assertIsString($output);
        self::assertStringContainsString('SELECT 1 AS id', $output);
        self::assertStringContainsString('Params:  0', $output);
    }

    public function testGetIteratorWalksTheRowsZtdBuffered(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $statement = ZtdPdo::fromPdo($native)->prepare('INSERT INTO users (name) VALUES (?) RETURNING id, name');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->execute(['linus']);

        self::assertSame([['id' => 1, 0 => 1, 'name' => 'linus', 1 => 'linus']], iterator_to_array($statement->getIterator()));
    }

    public function testGetIteratorWalksTheDriversOwnCursorWhereNothingWasBuffered(): void
    {
        $native = new PDO('sqlite::memory:');
        $inner = $native->prepare('SELECT 1 AS id');
        self::assertNotFalse($inner);
        $session = (new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory())->create(new \ZtdQuery\Adapter\Pdo\Driver\PdoConnection($native), ZtdConfig::default());
        $statement = new ZtdPdoStatement($inner, $session, null);
        $statement->execute();

        self::assertCount(1, iterator_to_array($statement->getIterator()));
    }








    public function testFetchAllAnswersFalseForAColumnTheBufferedRowDoesNotHold(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $statement = ZtdPdo::fromPdo($native)->prepare('INSERT INTO users (name) VALUES (?) RETURNING id, name');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->execute(['linus']);

        self::assertSame([false], $statement->fetchAll(PDO::FETCH_COLUMN, 5));
    }

    public function testFetchAllAnswersEveryBufferedRowAndNotJustTheFirst(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $ztdPdo = ZtdPdo::fromPdo($pdo);
        $statement = $ztdPdo->prepare("INSERT INTO users (name) VALUES ('ada'), ('grace') RETURNING id, name");
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->execute();

        self::assertSame(
            [['id' => 1, 'name' => 'ada'], ['id' => 2, 'name' => 'grace']],
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }
    /**
     * @throws ReflectionException
     */
    public function testSimulatedWritesWithoutReturningHaveNoFetchableRows(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $statement = ZtdPdo::fromPdo($pdo)->prepare('INSERT INTO users VALUES (1)');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        self::assertTrue($statement->execute());
        self::assertSame(1, $statement->rowCount());
        self::assertFalse($statement->fetch());
        self::assertSame([], $statement->fetchAll());
        self::assertFalse($statement->fetchColumn());
        self::assertFalse($statement->fetchObject());
    }

    public function testBufferedColumnFetchDefaultsToTheFirstColumnAndThenExhausts(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $statement = ZtdPdo::fromPdo($native)->prepare('INSERT INTO users (name) VALUES (?) RETURNING id, name');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->execute(['linus']);
        self::assertSame(1, $statement->fetchColumn());
        self::assertFalse($statement->fetchColumn());
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $statement = ZtdPdo::fromPdo($native)->prepare('INSERT INTO users (name) VALUES (?) RETURNING id, name');
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        $statement->execute(['linus']);
        self::assertSame([1], $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testPassthroughFetchAllPreservesKeyPairKeys(): void
    {
        $native = new PDO('sqlite::memory:');
        $inner = $native->prepare("SELECT 'alice' AS name, 7 AS score UNION ALL SELECT 'bob', 9");
        self::assertNotFalse($inner);
        $session = (new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory())->create(new \ZtdQuery\Adapter\Pdo\Driver\PdoConnection($native), ZtdConfig::default());
        $statement = new ZtdPdoStatement($inner, $session, null);
        self::assertTrue($statement->execute());
        self::assertSame(['alice' => 7, 'bob' => 9], $statement->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    public function testPassthroughFetchAllForwardsColumnAndCallbackArguments(): void
    {
        $native = new PDO('sqlite::memory:');
        $inner = $native->prepare("SELECT 1 AS id, 'alice' AS name");
        self::assertNotFalse($inner);
        $session = (new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory())->create(new \ZtdQuery\Adapter\Pdo\Driver\PdoConnection($native), ZtdConfig::default());
        $statement = new ZtdPdoStatement($inner, $session, null);
        self::assertTrue($statement->execute());
        self::assertSame(['alice'], $statement->fetchAll(PDO::FETCH_COLUMN, 1));
        self::assertTrue($statement->execute());
        self::assertSame(['1:alice'], $statement->fetchAll(PDO::FETCH_FUNC, static fn (int $id, string $name): string => $id . ':' . $name));
    }





    public function testBufferedColumnFetchAllReturnsEveryRow(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $statement = ZtdPdo::fromPdo($native)->query("INSERT INTO users VALUES (1, 'Ada'), (2, 'Grace') RETURNING id, name");
        self::assertInstanceOf(ZtdPdoStatement::class, $statement);
        self::assertSame(['Ada', 'Grace'], $statement->fetchAll(PDO::FETCH_COLUMN, 1));
        self::assertSame([], $statement->fetchAll(PDO::FETCH_COLUMN, 1));
    }

    /**
     * @throws ReflectionException When object hydration fails.
     */
    public function testFetchObjectDelegatesWithoutABufferedResult(): void
    {
        $native = new PDO('sqlite::memory:');
        $inner = $native->query("SELECT 7 AS id, 'Ada' AS name");
        self::assertNotFalse($inner);
        $session = (new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory())->create(new \ZtdQuery\Adapter\Pdo\Driver\PdoConnection($native), ZtdConfig::default());
        $statement = new ZtdPdoStatement($inner, $session, null);
        $row = $statement->fetchObject();
        self::assertIsObject($row);
        self::assertSame(['id' => 7, 'name' => 'Ada'], get_object_vars($row));
        self::assertFalse($statement->fetchObject());
    }
}
