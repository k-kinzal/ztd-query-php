<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Adapter\Pdo\PdoConnection;
use ZtdQuery\Adapter\Pdo\PdoStatement;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Adapter\Pdo\ZtdPdoStatement;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\CopySupport;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Session;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ZtdPdo::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(PdoStatement::class)]
#[UsesClass(ZtdPdoException::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\DriverSessionFactory::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\PdoFetchMode::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\PdoParameterBinder::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\PdoPreparedExecution::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\PostgreSqlCopy::class)]
#[UsesClass(ZtdPdoStatement::class)]
final class ZtdPdoTest extends TestCase
{
    public function testItBuildsItsSessionWithAnExplicitFactory(): void
    {
        $rewriter = static::createStub(SqlRewriter::class);
        $mockFactory = static::createMock(SessionFactory::class);
        $mockFactory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static fn (ConnectionInterface $connection, ZtdConfig $config): Session => new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection));

        $ztdPdo = new ZtdPdo('sqlite::memory:', null, null, null, null, $mockFactory);

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testFromPdoUsesExplicitSessionFactory(): void
    {
        $rewriter = static::createStub(SqlRewriter::class);
        $mockFactory = static::createMock(SessionFactory::class);
        $mockFactory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static fn (ConnectionInterface $connection, ZtdConfig $config): Session => new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection));

        $pdo = new PDO('sqlite::memory:');
        $ztdPdo = ZtdPdo::fromPdo($pdo, null, $mockFactory);

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testAutoDetectionForSqliteDriver(): void
    {
        (fn () => class_exists('ZtdQuery\\Platform\\Sqlite\\SqliteSessionFactory') || self::markTestSkipped('ztd-query-sqlite package is not installed.'))();

        $pdo = new PDO('sqlite::memory:');
        $ztdPdo = ZtdPdo::fromPdo($pdo);

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testZtdToggleWithExplicitFactory(): void
    {
        $rewriter = static::createStub(SqlRewriter::class);
        $mockFactory = static::createMock(SessionFactory::class);
        $mockFactory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static fn (ConnectionInterface $connection, ZtdConfig $config): Session => new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection));

        $pdo = new PDO('sqlite::memory:');
        $ztdPdo = ZtdPdo::fromPdo($pdo, null, $mockFactory);

        self::assertTrue($ztdPdo->isZtdEnabled());

        $ztdPdo->disableZtd();
        self::assertFalse($ztdPdo->isZtdEnabled());

        $ztdPdo->enableZtd();
        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testSessionFactoryCalledOncePerInstance(): void
    {
        $callCount = 0;
        $rewriter = static::createStub(SqlRewriter::class);
        $mockFactory = static::createMock(SessionFactory::class);
        $mockFactory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use (&$callCount, $rewriter): Session {
                $callCount++;

                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });

        $pdo = new PDO('sqlite::memory:');
        $ztdPdo = ZtdPdo::fromPdo($pdo, null, $mockFactory);

        $ztdPdo->disableZtd();
        $ztdPdo->enableZtd();
        $ztdPdo->isZtdEnabled();

        self::assertSame(1, $callCount);
    }

    public function testExplicitConfigPassedToFactory(): void
    {
        $expectedConfig = ZtdConfig::default();
        $receivedConfig = null;

        $rewriter = static::createStub(SqlRewriter::class);
        $mockFactory = static::createMock(SessionFactory::class);
        $mockFactory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use (&$receivedConfig, $rewriter): Session {
                $receivedConfig = $config;

                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });

        $pdo = new PDO('sqlite::memory:');
        ZtdPdo::fromPdo($pdo, $expectedConfig, $mockFactory);

        self::assertSame($expectedConfig, $receivedConfig);
    }

    public function testExecRunsEachStatementSequentiallyAndReturnsLastAffectedRows(): void
    {
        $store = new ShadowStore();
        $rewriter = static::createMock(SqlRewriter::class);
        $rewriter->expects(self::exactly(3))
            ->method('splitStatements')
            ->willReturnCallback(static fn (string $sql): array => match ($sql) {
                'first; second' => ['first', 'second'],
                default => [$sql],
            });
        $rewriter->expects(self::exactly(2))
            ->method('rewrite')
            ->willReturnCallback(static fn (string $sql): RewritePlan => match ($sql) {
                'first' => new RewritePlan(
                    'SELECT 1 AS id UNION ALL SELECT 2 AS id',
                    QueryKind::WRITE_SIMULATED,
                    new InsertMutation('first_items'),
                ),
                'second' => new RewritePlan(
                    'SELECT 3 AS id',
                    QueryKind::WRITE_SIMULATED,
                    new InsertMutation('second_items'),
                ),
                default => throw new RuntimeException("Unexpected SQL: $sql"),
            });
        $factory = static::createMock(SessionFactory::class);
        $typeResolver = static::createStub(ResultColumnTypeResolver::class);
        $typeResolver->method('resolve')->willReturn(new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'));
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static fn (ConnectionInterface $connection, ZtdConfig $config): Session => new Session(
                $rewriter,
                $store,
                new ResultSelectRunner(),
                $config,
                $connection,
                resultColumnTypeResolver: $typeResolver,
            ));
        $ztdPdo = ZtdPdo::fromPdo(new PDO('sqlite::memory:'), null, $factory);

        self::assertSame(1, $ztdPdo->exec('first; second'));
        self::assertSame([['id' => 1], ['id' => 2]], $store->get('first_items'));
        self::assertSame([['id' => 3]], $store->get('second_items'));
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

    public function testExecStopsBatchWhenFirstStatementFails(): void
    {
        $rewriter = static::createMock(SqlRewriter::class);
        $rewriter->expects(self::exactly(2))
            ->method('splitStatements')
            ->willReturnCallback(static fn (string $sql): array => match ($sql) {
                'first; second' => ['first', 'second'],
                default => [$sql],
            });
        $rewriter->expects(self::once())
            ->method('rewrite')
            ->with('first')
            ->willReturn(new RewritePlan('SELECT * FROM missing_table', QueryKind::READ));
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static fn (ConnectionInterface $connection, ZtdConfig $config): Session => new Session(
                $rewriter,
                new ShadowStore(),
                new ResultSelectRunner(),
                $config,
                $connection,
            ));
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $ztdPdo = ZtdPdo::fromPdo($pdo, null, $factory);

        self::assertFalse($ztdPdo->exec('first; second'));
    }

    public function testExecStopsBatchWhenLaterStatementFails(): void
    {
        $rewriter = static::createMock(SqlRewriter::class);
        $rewriter->expects(self::exactly(3))
            ->method('splitStatements')
            ->willReturnCallback(static fn (string $sql): array => match ($sql) {
                'first; second; third' => ['first', 'second', 'third'],
                default => [$sql],
            });
        $rewriter->expects(self::exactly(2))
            ->method('rewrite')
            ->willReturnCallback(static fn (string $sql): RewritePlan => match ($sql) {
                'first' => new RewritePlan('SELECT 1', QueryKind::READ),
                'second' => new RewritePlan('SELECT * FROM missing_table', QueryKind::READ),
                default => throw new RuntimeException("Unexpected SQL: $sql"),
            });
        $factory = static::createMock(SessionFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static fn (ConnectionInterface $connection, ZtdConfig $config): Session => new Session(
                $rewriter,
                new ShadowStore(),
                new ResultSelectRunner(),
                $config,
                $connection,
            ));
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $ztdPdo = ZtdPdo::fromPdo($pdo, null, $factory);

        self::assertFalse($ztdPdo->exec('first; second; third'));
    }

    public function testItHandsAnExplicitConfigToTheFactory(): void
    {
        $expectedConfig = ZtdConfig::default();
        $receivedConfig = null;

        $rewriter = static::createStub(SqlRewriter::class);
        $mockFactory = static::createMock(SessionFactory::class);
        $mockFactory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (ConnectionInterface $connection, ZtdConfig $config) use (&$receivedConfig, $rewriter): Session {
                $receivedConfig = $config;

                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            });

        new ZtdPdo('sqlite::memory:', null, null, null, $expectedConfig, $mockFactory);

        self::assertSame($expectedConfig, $receivedConfig);
    }

    public function testEnableZtdPutsTheShadowBackInFrontOfTheDatabase(): void
    {
        $ztdPdo = $this->providerShadowedUsers();
        $ztdPdo->disableZtd();

        $ztdPdo->enableZtd();

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testDisableZtdLetsStatementsReachTheDatabase(): void
    {
        $ztdPdo = $this->providerShadowedUsers();

        $ztdPdo->disableZtd();

        self::assertFalse($ztdPdo->isZtdEnabled());
    }

    public function testIsZtdEnabledSaysWritesAreShadowedFromTheStart(): void
    {
        self::assertTrue($this->providerShadowedUsers()->isZtdEnabled());
    }

    public function testPrepareAnswersAStatementThatShadowsWhatItIsRunWith(): void
    {
        $ztdPdo = $this->providerShadowedUsers();

        $statement = $ztdPdo->prepare('SELECT * FROM users');

        self::assertNotFalse($statement);
    }

    public function testPrepareRefusesADriverOptionPdoCannotBeGiven(): void
    {
        $ztdPdo = $this->providerShadowedUsers();

        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('must be a PDO attribute set to a bool, int or string');

        $ztdPdo->prepare('SELECT * FROM users', ['cursor' => 1.5]);
    }

    public function testPrepareHandsTheStatementStraightToPdoWhileZtdIsOff(): void
    {
        $ztdPdo = $this->providerShadowedUsers();
        $ztdPdo->disableZtd();

        $statement = $ztdPdo->prepare('SELECT * FROM users');

        self::assertNotInstanceOf(ZtdPdoStatement::class, $statement);
    }

    public function testQueryReadsTheShadowRatherThanTheTable(): void
    {
        $ztdPdo = $this->providerShadowedUsers();

        $statement = $ztdPdo->query('SELECT * FROM users');

        self::assertSame([], $statement === false ? [false] : $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testQueryReadsBackWhatWasWrittenThroughZtd(): void
    {
        $ztdPdo = $this->providerShadowedUsers();
        $ztdPdo->exec("INSERT INTO users (id, name) VALUES (3, 'linus')");

        $statement = $ztdPdo->query('SELECT * FROM users');

        self::assertSame([['id' => 3, 'name' => 'linus']], $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testQueryReadsInTheFetchModeItIsGiven(): void
    {
        $ztdPdo = $this->providerShadowedUsers();
        $ztdPdo->exec("INSERT INTO users (id, name) VALUES (3, 'linus')");

        $statement = $ztdPdo->query('SELECT * FROM users', PDO::FETCH_NUM);

        self::assertSame([[3, 'linus']], $statement === false ? [] : $statement->fetchAll());
    }

    public function testConnectOpensAConnectionWithZtdAlreadyInFrontOfIt(): void
    {
        self::assertInstanceOf(ZtdPdo::class, ZtdPdo::connect('sqlite::memory:'));
    }

    public function testBeginTransactionOpensOneOnTheShadowAsWellAsTheDatabase(): void
    {
        $ztdPdo = $this->providerShadowedUsers();

        self::assertSame([true, true], [$ztdPdo->beginTransaction(), $ztdPdo->inTransaction()]);
    }

    public function testCommitKeepsWhatTheTransactionWroteToTheShadow(): void
    {
        $ztdPdo = $this->providerShadowedUsers();
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
        $ztdPdo = $this->providerShadowedUsers();
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
        self::assertFalse($this->providerShadowedUsers()->inTransaction());
    }

    public function testLastInsertIdAnswersTheKeyTheShadowGaveTheRowItWrote(): void
    {
        $ztdPdo = $this->providerShadowedUsers();
        $ztdPdo->exec("INSERT INTO users (name) VALUES ('linus')");

        self::assertSame('1', $ztdPdo->lastInsertId());
    }

    public function testErrorCodeAnswersWhatTheDriverSaysWentWrongLast(): void
    {
        self::assertSame('00000', $this->providerShadowedUsers()->errorCode());
    }

    public function testErrorInfoAnswersWhatTheDriverSaysAboutTheLastFailure(): void
    {
        self::assertSame('00000', $this->providerShadowedUsers()->errorInfo()[0]);
    }

    public function testGetAttributeReadsTheAttributeOffTheConnectionItWraps(): void
    {
        self::assertSame('sqlite', $this->providerShadowedUsers()->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function testSetAttributeSetsTheAttributeOnTheConnectionItWraps(): void
    {
        $ztdPdo = $this->providerShadowedUsers();

        $ztdPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_NUM);

        self::assertSame(PDO::FETCH_NUM, $ztdPdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testQuoteWritesAValueTheWayTheDriverWouldQuoteIt(): void
    {
        self::assertSame("'ada'", $this->providerShadowedUsers()->quote('ada'));
    }

    public function testGetAvailableDriversAnswersTheDriversPdoWasBuiltWith(): void
    {
        self::assertContains('sqlite', ZtdPdo::getAvailableDrivers());
    }

    public function testPgsqlCopyToArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $this->providerShadowedUsers()->pgsqlCopyToArray('users');
    }

    public function testCopyToArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $this->providerShadowedUsers()->copyToArray('users');
    }

    public function testPgsqlCopyFromArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $this->providerShadowedUsers()->pgsqlCopyFromArray('users', ["1\tada\n"]);
    }

    public function testCopyFromArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $this->providerShadowedUsers()->copyFromArray('users', ["1\tada\n"]);
    }

    public function testPgsqlCopyToFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $this->providerShadowedUsers()->pgsqlCopyToFile('users', '/dev/null');
    }

    public function testCopyToFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $this->providerShadowedUsers()->copyToFile('users', '/dev/null');
    }

    public function testPgsqlCopyFromFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $this->providerShadowedUsers()->pgsqlCopyFromFile('users', '/dev/null');
    }

    public function testCopyFromFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $this->providerShadowedUsers()->copyFromFile('users', '/dev/null');
    }

    /**
     * @return ZtdPdo A SQLite connection with ZTD in front of a two-row users table
     */
    public function providerShadowedUsers(): ZtdPdo
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $pdo->exec("INSERT INTO users (name) VALUES ('ada'), ('grace')");

        return ZtdPdo::fromPdo($pdo);
    }
}
