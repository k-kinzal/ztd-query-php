<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\Driver\PdoConnection;
use ZtdQuery\Adapter\Pdo\Driver\PdoStatement;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Adapter\Pdo\ZtdPdoStatement;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\CopySupport;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ZtdPdo::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ZtdPdoException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ZtdPdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(PdoConnection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(PdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\PostgreSqlCopy::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\StatementExecution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\Bindings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\BufferedRow::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\DriverSessionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\PreparedQuery::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[CoversClass(\ZtdQuery\Adapter\Pdo\Session\ConnectionExecution::class)]
#[CoversClass(\ZtdQuery\Adapter\Pdo\Session\CopyArguments::class)]
final class ZtdPdoTest extends TestCase
{
    public function testExplicitPlatformFactoryCreatesAnIsolatedSession(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo = ZtdPdo::fromPdo($native, factory: new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory());
        self::assertSame(1, $pdo->exec("INSERT INTO users VALUES (1, 'Alice')"));
        $result1 = $pdo->query('SELECT name FROM users');
        self::assertNotFalse($result1);
        self::assertSame('Alice', $result1->fetchColumn());
        $result2 = $native->query('SELECT COUNT(*) FROM users');
        self::assertNotFalse($result2);
        self::assertSame(0, $result2->fetchColumn());
        $pdo->disableZtd();
        $pdo->enableZtd();
        $result3 = $pdo->query('SELECT name FROM users');
        self::assertNotFalse($result3);
        self::assertSame('Alice', $result3->fetchColumn());
    }

    public function testBatchExecReturnsTheLastStatementCount(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo = ZtdPdo::fromPdo($native);
        self::assertSame(1, $pdo->exec("INSERT INTO users VALUES (1, 'Alice'), (2, 'Bob'); UPDATE users SET name = 'Carol' WHERE id = 2"));
        $result4 = $pdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($result4);
        self::assertSame([['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Carol']], $result4->fetchAll(PDO::FETCH_ASSOC));
    }





    public function testAutoDetectionForSqliteDriver(): void
    {
        (fn () => class_exists('ZtdQuery\\Platform\\Sqlite\\SqliteSessionFactory') || self::markTestSkipped('ztd-query-sqlite package is not installed.'))();

        $pdo = new PDO('sqlite::memory:');
        $ztdPdo = ZtdPdo::fromPdo($pdo);

        self::assertTrue($ztdPdo->isZtdEnabled());
    }









    public function testExecRejectsRawPostgreSqlCopy(): void
    {
        $rewriter = static::createStub(SqlRewriter::class);
        $copySupport = static::createStub(CopySupport::class);
        $copySupport->method('isCopyStatement')->willReturn(true);
        $factory = static::createStub(SessionFactory::class);
        $factory->method('create')
            ->willReturnCallback(static fn (ConnectionInterface $connection, ZtdConfig $config): Session => new Session(
                $rewriter,
                new ShadowStore(),
                new ResultSelectRunner(),
                $config,
                $connection,
                copySupport: $copySupport,
            ));
        $ztdPdo = ZtdPdo::fromPdo(new PDO('sqlite::memory:'), null, $factory);

        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage(
            'ZTD Write Protection: Raw PostgreSQL COPY cannot preserve shadow isolation; '
            . 'use the pgsqlCopyToArray(), pgsqlCopyFromArray(), pgsqlCopyToFile(), or pgsqlCopyFromFile() methods.',
        );

        $ztdPdo->exec('COPY users TO STDOUT');
    }







    public function testEnableZtdPutsTheShadowBackInFrontOfTheDatabase(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);
        $ztdPdo->disableZtd();

        $ztdPdo->enableZtd();

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testDisableZtdLetsStatementsReachTheDatabase(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $ztdPdo->disableZtd();

        self::assertFalse($ztdPdo->isZtdEnabled());
    }

    public function testIsZtdEnabledSaysWritesAreShadowedFromTheStart(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testPrepareAnswersAStatementThatShadowsWhatItIsRunWith(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $statement = $ztdPdo->prepare('SELECT * FROM users');

        self::assertNotFalse($statement);
    }


    public function testPrepareHandsTheStatementStraightToPdoWhileZtdIsOff(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);
        $ztdPdo->disableZtd();

        $statement = $ztdPdo->prepare('SELECT * FROM users');

        self::assertNotInstanceOf(ZtdPdoStatement::class, $statement);
    }

    public function testQueryReadsTheShadowRatherThanTheTable(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $statement = $ztdPdo->query('SELECT * FROM users');

        self::assertSame([], $statement === false ? [false] : $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testQueryReadsBackWhatWasWrittenThroughZtd(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);
        $ztdPdo->exec("INSERT INTO users (id, name) VALUES (3, 'linus')");

        $statement = $ztdPdo->query('SELECT * FROM users');

        self::assertSame([['id' => 3, 'name' => 'linus']], $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testQueryReadsInTheFetchModeItIsGiven(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);
        $ztdPdo->exec("INSERT INTO users (id, name) VALUES (3, 'linus')");

        $statement = $ztdPdo->query('SELECT * FROM users', PDO::FETCH_NUM);

        self::assertSame([[3, 'linus']], $statement === false ? [] : $statement->fetchAll());
    }

    public function testConnectOpensAConnectionWithZtdAlreadyInFrontOfIt(): void
    {
        self::assertSame(ZtdPdo::class, ZtdPdo::connect('sqlite::memory:')::class);
    }

    public function testBeginTransactionOpensOneOnTheShadowAsWellAsTheDatabase(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        self::assertSame([true, true], [$ztdPdo->beginTransaction(), $ztdPdo->inTransaction()]);
    }

    public function testCommitKeepsWhatTheTransactionWroteToTheShadow(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);
        $ztdPdo->beginTransaction();
        $ztdPdo->exec("INSERT INTO users (id, name) VALUES (3, 'linus')");

        $committed = $ztdPdo->commit();

        $statement = $ztdPdo->query('SELECT * FROM users');
        self::assertSame([true, [['id' => 3, 'name' => 'linus']]], [
            $committed,
            $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function testRollBackTakesBackWhatTheTransactionWroteToTheShadow(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);
        $ztdPdo->beginTransaction();
        $ztdPdo->exec("INSERT INTO users (id, name) VALUES (3, 'linus')");

        $rolledBack = $ztdPdo->rollBack();

        $statement = $ztdPdo->query('SELECT * FROM users');
        self::assertSame([true, []], [
            $rolledBack,
            $statement === false ? [false] : $statement->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function testInTransactionSaysNothingIsOpenBeforeOneIsBegun(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        self::assertFalse($ztdPdo->inTransaction());
    }

    public function testLastInsertIdAnswersTheKeyTheShadowGaveTheRowItWrote(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);
        $ztdPdo->exec("INSERT INTO users (name) VALUES ('linus')");

        self::assertSame('1', $ztdPdo->lastInsertId());
    }

    public function testErrorCodeAnswersWhatTheDriverSaysWentWrongLast(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        self::assertSame('00000', $ztdPdo->errorCode());
    }

    public function testErrorInfoAnswersWhatTheDriverSaysAboutTheLastFailure(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        self::assertSame('00000', $ztdPdo->errorInfo()[0]);
    }

    public function testGetAttributeReadsTheAttributeOffTheConnectionItWraps(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        self::assertSame('sqlite', $ztdPdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function testSetAttributeSetsTheAttributeOnTheConnectionItWraps(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $ztdPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_NUM);

        self::assertSame(PDO::FETCH_NUM, $ztdPdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testQuoteWritesAValueTheWayTheDriverWouldQuoteIt(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        self::assertSame("'ada'", $ztdPdo->quote('ada'));
    }

    public function testGetAvailableDriversAnswersTheDriversPdoWasBuiltWith(): void
    {
        self::assertContains('sqlite', ZtdPdo::getAvailableDrivers());
    }

    public function testPgsqlCopyToArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $ztdPdo->pgsqlCopyToArray('users', fields: 'id');
    }

    public function testCopyToArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $ztdPdo->copyToArray('users');
    }

    public function testPgsqlCopyFromArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $ztdPdo->pgsqlCopyFromArray('users', ["1\tada\n"]);
    }

    public function testCopyFromArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $ztdPdo->copyFromArray('users', ["1\tada\n"]);
    }

    public function testPgsqlCopyToFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $ztdPdo->pgsqlCopyToFile('users', '/dev/null', fields: 'id');
    }

    public function testCopyToFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $ztdPdo->copyToFile('users', '/dev/null');
    }

    public function testPgsqlCopyFromFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $ztdPdo->pgsqlCopyFromFile('users', '/dev/null', fields: 'id');
    }

    public function testCopyFromFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $ztdPdo = ZtdPdo::fromPdo($native);

        $ztdPdo->copyFromFile('users', '/dev/null');
    }


    public function testPrepareRefusesACopyWrittenAsRawSql(): void
    {
        $rewriter = static::createStub(SqlRewriter::class);
        $copySupport = static::createStub(CopySupport::class);
        $copySupport->method('isCopyStatement')->willReturn(true);
        $factory = static::createStub(SessionFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn (ConnectionInterface $connection, ZtdConfig $config): Session => new Session(
                $rewriter,
                new ShadowStore(),
                new ResultSelectRunner(),
                $config,
                $connection,
                copySupport: $copySupport,
            ),
        );
        $ztdPdo = ZtdPdo::fromPdo(new PDO('sqlite::memory:'), null, $factory);

        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('ZTD Write Protection: Raw PostgreSQL COPY');

        $ztdPdo->prepare('COPY users TO STDOUT');
    }
    public function testFromPdoRetainsTheExistingConnectionAndItsOptions(): void
    {
        $native = new PDO('sqlite::memory:', options: [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $native->exec('CREATE TABLE items (id INTEGER PRIMARY KEY)');
        $pdo = ZtdPdo::fromPdo($native);
        self::assertSame(1, $pdo->exec('INSERT INTO items VALUES (7)'));
        $result5 = $pdo->query('SELECT id FROM items');
        self::assertNotFalse($result5);
        self::assertSame(['id' => 7], $result5->fetch());
        $result6 = $native->query('SELECT COUNT(*) FROM items');
        self::assertNotFalse($result6);
        self::assertSame(0, $result6->fetchColumn());
    }

    public function testPgsqlCopyToArrayValidatesNativeArgumentsBeforeDispatch(): void
    {
        $pdo = ZtdPdo::fromPdo(new PDO('sqlite::memory:'));
        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('PostgreSQL COPY argument $tableName must be a string, int given.');
        $pdo->pgsqlCopyToArray(1);
    }

    public function testPgsqlCopyFromArrayValidatesOptionalFields(): void
    {
        $pdo = ZtdPdo::fromPdo(new PDO('sqlite::memory:'));
        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('PostgreSQL COPY argument $fields must be a string, float given.');
        $pdo->pgsqlCopyFromArray('items', [], fields: 1.5);
    }

    public function testCopyFromArrayRejectsNonStringRows(): void
    {
        $pdo = ZtdPdo::fromPdo(new PDO('sqlite::memory:'));
        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('PostgreSQL COPY rows must be strings, int given.');
        $pdo->copyFromArray('items', [1]);
    }

    public function testPrepareWrapsUnsupportedStatementsForPdoConsumers(): void
    {
        $pdo = ZtdPdo::fromPdo(new PDO('sqlite::memory:'));
        try {
            $pdo->prepare('VACUUM');
            self::fail('Unsupported maintenance statements must be rejected.');
        } catch (ZtdPdoException $failure) {
            self::assertStringContainsString('Statement type not supported', $failure->getMessage());
            self::assertSame(0, $failure->getCode());
            self::assertInstanceOf(\ZtdQuery\Connection\Exception\DatabaseException::class, $failure->getPrevious());
        }
    }


    public function testPgsqlCopyToArrayRejectsInvalidFieldLists(): void
    {
        $pdo = ZtdPdo::fromPdo(new PDO('sqlite::memory:'));
        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('PostgreSQL COPY argument $fields must be a string, float given.');
        $pdo->pgsqlCopyToArray('items', fields: 1.5);
    }

    public function testPgsqlCopyToFileRejectsInvalidFieldLists(): void
    {
        $pdo = ZtdPdo::fromPdo(new PDO('sqlite::memory:'));
        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('PostgreSQL COPY argument $fields must be a string, float given.');
        $pdo->pgsqlCopyToFile('items', '/dev/null', fields: 1.5);
    }

    public function testPgsqlCopyFromFileRejectsInvalidFieldLists(): void
    {
        $pdo = ZtdPdo::fromPdo(new PDO('sqlite::memory:'));
        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('PostgreSQL COPY argument $fields must be a string, float given.');
        $pdo->pgsqlCopyFromFile('items', '/dev/null', fields: 1.5);
    }
}
