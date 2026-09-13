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
        $native1 = new PDO('sqlite::memory:');
        $native1->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native1->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection1 = ZtdPdo::fromPdo($native1);
        $ztdPdo = $connection1;
        $ztdPdo->disableZtd();

        $ztdPdo->enableZtd();

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testDisableZtdLetsStatementsReachTheDatabase(): void
    {
        $native2 = new PDO('sqlite::memory:');
        $native2->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native2->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection2 = ZtdPdo::fromPdo($native2);
        $ztdPdo = $connection2;

        $ztdPdo->disableZtd();

        self::assertFalse($ztdPdo->isZtdEnabled());
    }

    public function testIsZtdEnabledSaysWritesAreShadowedFromTheStart(): void
    {
        $native3 = new PDO('sqlite::memory:');
        $native3->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native3->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection3 = ZtdPdo::fromPdo($native3);
        $ztdPdo = $connection3;

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testPrepareAnswersAStatementThatShadowsWhatItIsRunWith(): void
    {
        $native4 = new PDO('sqlite::memory:');
        $native4->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native4->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection4 = ZtdPdo::fromPdo($native4);
        $ztdPdo = $connection4;

        $statement = $ztdPdo->prepare('SELECT * FROM users');

        self::assertNotFalse($statement);
    }


    public function testPrepareHandsTheStatementStraightToPdoWhileZtdIsOff(): void
    {
        $native5 = new PDO('sqlite::memory:');
        $native5->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native5->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection5 = ZtdPdo::fromPdo($native5);
        $ztdPdo = $connection5;
        $ztdPdo->disableZtd();

        $statement = $ztdPdo->prepare('SELECT * FROM users');

        self::assertNotInstanceOf(ZtdPdoStatement::class, $statement);
    }

    public function testQueryReadsTheShadowRatherThanTheTable(): void
    {
        $native6 = new PDO('sqlite::memory:');
        $native6->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native6->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection6 = ZtdPdo::fromPdo($native6);
        $ztdPdo = $connection6;

        $statement = $ztdPdo->query('SELECT * FROM users');

        self::assertSame([], $statement === false ? [false] : $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testQueryReadsBackWhatWasWrittenThroughZtd(): void
    {
        $native7 = new PDO('sqlite::memory:');
        $native7->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native7->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection7 = ZtdPdo::fromPdo($native7);
        $ztdPdo = $connection7;
        $ztdPdo->exec("INSERT INTO users (id, name) VALUES (3, 'linus')");

        $statement = $ztdPdo->query('SELECT * FROM users');

        self::assertSame([['id' => 3, 'name' => 'linus']], $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testQueryReadsInTheFetchModeItIsGiven(): void
    {
        $native8 = new PDO('sqlite::memory:');
        $native8->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native8->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection8 = ZtdPdo::fromPdo($native8);
        $ztdPdo = $connection8;
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
        $native9 = new PDO('sqlite::memory:');
        $native9->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native9->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection9 = ZtdPdo::fromPdo($native9);
        $ztdPdo = $connection9;

        self::assertSame([true, true], [$ztdPdo->beginTransaction(), $ztdPdo->inTransaction()]);
    }

    public function testCommitKeepsWhatTheTransactionWroteToTheShadow(): void
    {
        $native10 = new PDO('sqlite::memory:');
        $native10->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native10->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection10 = ZtdPdo::fromPdo($native10);
        $ztdPdo = $connection10;
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
        $native11 = new PDO('sqlite::memory:');
        $native11->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native11->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection11 = ZtdPdo::fromPdo($native11);
        $ztdPdo = $connection11;
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
        $native12 = new PDO('sqlite::memory:');
        $native12->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native12->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection12 = ZtdPdo::fromPdo($native12);
        $ztdPdo = $connection12;

        self::assertFalse($ztdPdo->inTransaction());
    }

    public function testLastInsertIdAnswersTheKeyTheShadowGaveTheRowItWrote(): void
    {
        $native13 = new PDO('sqlite::memory:');
        $native13->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native13->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection13 = ZtdPdo::fromPdo($native13);
        $ztdPdo = $connection13;
        $ztdPdo->exec("INSERT INTO users (name) VALUES ('linus')");

        self::assertSame('1', $ztdPdo->lastInsertId());
    }

    public function testErrorCodeAnswersWhatTheDriverSaysWentWrongLast(): void
    {
        $native14 = new PDO('sqlite::memory:');
        $native14->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native14->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection14 = ZtdPdo::fromPdo($native14);
        $ztdPdo = $connection14;

        self::assertSame('00000', $ztdPdo->errorCode());
    }

    public function testErrorInfoAnswersWhatTheDriverSaysAboutTheLastFailure(): void
    {
        $native15 = new PDO('sqlite::memory:');
        $native15->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native15->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection15 = ZtdPdo::fromPdo($native15);
        $ztdPdo = $connection15;

        self::assertSame('00000', $ztdPdo->errorInfo()[0]);
    }

    public function testGetAttributeReadsTheAttributeOffTheConnectionItWraps(): void
    {
        $native16 = new PDO('sqlite::memory:');
        $native16->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native16->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection16 = ZtdPdo::fromPdo($native16);
        $ztdPdo = $connection16;

        self::assertSame('sqlite', $ztdPdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function testSetAttributeSetsTheAttributeOnTheConnectionItWraps(): void
    {
        $native17 = new PDO('sqlite::memory:');
        $native17->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native17->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection17 = ZtdPdo::fromPdo($native17);
        $ztdPdo = $connection17;

        $ztdPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_NUM);

        self::assertSame(PDO::FETCH_NUM, $ztdPdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testQuoteWritesAValueTheWayTheDriverWouldQuoteIt(): void
    {
        $native18 = new PDO('sqlite::memory:');
        $native18->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native18->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection18 = ZtdPdo::fromPdo($native18);
        $ztdPdo = $connection18;

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

        $native19 = new PDO('sqlite::memory:');
        $native19->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native19->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection19 = ZtdPdo::fromPdo($native19);
        $ztdPdo = $connection19;

        $ztdPdo->pgsqlCopyToArray('users');
    }

    public function testCopyToArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native20 = new PDO('sqlite::memory:');
        $native20->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native20->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection20 = ZtdPdo::fromPdo($native20);
        $ztdPdo = $connection20;

        $ztdPdo->copyToArray('users');
    }

    public function testPgsqlCopyFromArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native21 = new PDO('sqlite::memory:');
        $native21->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native21->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection21 = ZtdPdo::fromPdo($native21);
        $ztdPdo = $connection21;

        $ztdPdo->pgsqlCopyFromArray('users', ["1\tada\n"]);
    }

    public function testCopyFromArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native22 = new PDO('sqlite::memory:');
        $native22->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native22->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection22 = ZtdPdo::fromPdo($native22);
        $ztdPdo = $connection22;

        $ztdPdo->copyFromArray('users', ["1\tada\n"]);
    }

    public function testPgsqlCopyToFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native23 = new PDO('sqlite::memory:');
        $native23->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native23->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection23 = ZtdPdo::fromPdo($native23);
        $ztdPdo = $connection23;

        $ztdPdo->pgsqlCopyToFile('users', '/dev/null');
    }

    public function testCopyToFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native24 = new PDO('sqlite::memory:');
        $native24->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native24->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection24 = ZtdPdo::fromPdo($native24);
        $ztdPdo = $connection24;

        $ztdPdo->copyToFile('users', '/dev/null');
    }

    public function testPgsqlCopyFromFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native25 = new PDO('sqlite::memory:');
        $native25->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native25->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection25 = ZtdPdo::fromPdo($native25);
        $ztdPdo = $connection25;

        $ztdPdo->pgsqlCopyFromFile('users', '/dev/null');
    }

    public function testCopyFromFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $native26 = new PDO('sqlite::memory:');
        $native26->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $native26->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");
        $connection26 = ZtdPdo::fromPdo($native26);
        $ztdPdo = $connection26;

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
            self::assertInstanceOf(\ZtdQuery\Connection\Exception\DatabaseException::class, $failure->getPrevious());
        }
    }

}
