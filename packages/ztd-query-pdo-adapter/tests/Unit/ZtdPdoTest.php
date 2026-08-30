<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fixtures\RecordingSessionFactory;
use Tests\Fixtures\RecordingSqlRewriter;
use ZtdQuery\Adapter\Pdo\Driver\PdoConnection;
use ZtdQuery\Adapter\Pdo\Driver\PdoStatement;
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
use ZtdQuery\Shadow\Mutation\Row\InsertMutation;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ZtdPdo::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(PdoStatement::class)]
#[UsesClass(ZtdPdoException::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\DriverSessionFactory::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Driver\PdoFetchMode::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Driver\PdoParameterBinder::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Driver\PdoPreparedExecution::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\PostgreSqlCopy::class)]
#[UsesClass(ZtdPdoStatement::class)]
final class ZtdPdoTest extends TestCase
{
    public function testItBuildsItsSessionWithAnExplicitFactory(): void
    {
        $rewriter = static::createStub(SqlRewriter::class);
        $mockFactory = RecordingSessionFactory::answeringWith($rewriter);

        $ztdPdo = new ZtdPdo('sqlite::memory:', null, null, null, null, $mockFactory);

        self::assertTrue($ztdPdo->isZtdEnabled());

        self::assertCount(1, $mockFactory->calls());
    }

    public function testFromPdoUsesExplicitSessionFactory(): void
    {
        $rewriter = static::createStub(SqlRewriter::class);
        $mockFactory = RecordingSessionFactory::answeringWith($rewriter);

        $pdo = new PDO('sqlite::memory:');
        $ztdPdo = ZtdPdo::fromPdo($pdo, null, $mockFactory);

        self::assertTrue($ztdPdo->isZtdEnabled());

        self::assertCount(1, $mockFactory->calls());
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
        $mockFactory = RecordingSessionFactory::answeringWith($rewriter);

        $pdo = new PDO('sqlite::memory:');
        $ztdPdo = ZtdPdo::fromPdo($pdo, null, $mockFactory);

        self::assertTrue($ztdPdo->isZtdEnabled());

        $ztdPdo->disableZtd();
        self::assertFalse($ztdPdo->isZtdEnabled());

        $ztdPdo->enableZtd();
        self::assertTrue($ztdPdo->isZtdEnabled());

        self::assertCount(1, $mockFactory->calls());
    }

    public function testSessionFactoryCalledOncePerInstance(): void
    {
        $callCount = 0;
        $rewriter = static::createStub(SqlRewriter::class);
        $mockFactory = new RecordingSessionFactory(
            static function (ConnectionInterface $connection, ZtdConfig $config) use (&$callCount, $rewriter): Session {
                $callCount++;

                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            },
        );

        $pdo = new PDO('sqlite::memory:');
        $ztdPdo = ZtdPdo::fromPdo($pdo, null, $mockFactory);

        $ztdPdo->disableZtd();
        $ztdPdo->enableZtd();
        $ztdPdo->isZtdEnabled();

        self::assertSame(1, $callCount);

        self::assertCount(1, $mockFactory->calls());
    }

    public function testExplicitConfigPassedToFactory(): void
    {
        $expectedConfig = ZtdConfig::default();
        $receivedConfig = null;

        $rewriter = static::createStub(SqlRewriter::class);
        $mockFactory = new RecordingSessionFactory(
            static function (ConnectionInterface $connection, ZtdConfig $config) use (&$receivedConfig, $rewriter): Session {
                $receivedConfig = $config;

                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            },
        );

        $pdo = new PDO('sqlite::memory:');
        ZtdPdo::fromPdo($pdo, $expectedConfig, $mockFactory);

        self::assertSame($expectedConfig, $receivedConfig);

        self::assertCount(1, $mockFactory->calls());
    }

    public function testExecRunsEachStatementSequentiallyAndReturnsLastAffectedRows(): void
    {
        $store = new ShadowStore();
        $rewriter = new RecordingSqlRewriter(
            static fn (string $sql): array => match ($sql) {
                'first; second' => ['first', 'second'],
                default => [$sql],
            },
            static fn (string $sql): RewritePlan => match ($sql) {
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
            },
        );
        $typeResolver = static::createStub(ResultColumnTypeResolver::class);
        $typeResolver->method('resolve')->willReturn(new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'));
        $factory = new RecordingSessionFactory(
            static fn (ConnectionInterface $connection, ZtdConfig $config): Session => new Session(
                $rewriter,
                $store,
                new ResultSelectRunner(),
                $config,
                $connection,
                resultColumnTypeResolver: $typeResolver,
            ),
        );
        $ztdPdo = ZtdPdo::fromPdo(new PDO('sqlite::memory:'), null, $factory);

        self::assertSame(1, $ztdPdo->exec('first; second'));
        self::assertSame([['id' => 1], ['id' => 2]], $store->get('first_items'));
        self::assertSame([['id' => 3]], $store->get('second_items'));

        self::assertCount(1, $factory->calls());

        self::assertCount(3, $rewriter->split);
        self::assertCount(2, $rewriter->rewritten);
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
        $rewriter = new RecordingSqlRewriter(
            static fn (string $sql): array => match ($sql) {
                'first; second' => ['first', 'second'],
                default => [$sql],
            },
            static fn (string $sql): RewritePlan => new RewritePlan('SELECT * FROM missing_table', QueryKind::READ),
        );
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $ztdPdo = ZtdPdo::fromPdo($pdo, null, $factory);

        self::assertFalse($ztdPdo->exec('first; second'));

        self::assertSame(['first; second', 'first'], $rewriter->split);
        self::assertSame(['first'], $rewriter->rewritten);
        self::assertCount(1, $factory->calls());
    }

    public function testExecStopsBatchWhenLaterStatementFails(): void
    {
        $rewriter = new RecordingSqlRewriter(
            static fn (string $sql): array => match ($sql) {
                'first; second; third' => ['first', 'second', 'third'],
                default => [$sql],
            },
            static fn (string $sql): RewritePlan => match ($sql) {
                'first' => new RewritePlan('SELECT 1', QueryKind::READ),
                'second' => new RewritePlan('SELECT * FROM missing_table', QueryKind::READ),
                default => throw new RuntimeException("Unexpected SQL: $sql"),
            },
        );
        $factory = RecordingSessionFactory::answeringWith($rewriter);
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $ztdPdo = ZtdPdo::fromPdo($pdo, null, $factory);

        self::assertFalse($ztdPdo->exec('first; second; third'));

        self::assertCount(3, $rewriter->split);
        self::assertCount(2, $rewriter->rewritten);
        self::assertCount(1, $factory->calls());
    }

    public function testItHandsAnExplicitConfigToTheFactory(): void
    {
        $expectedConfig = ZtdConfig::default();
        $receivedConfig = null;

        $rewriter = static::createStub(SqlRewriter::class);
        $mockFactory = new RecordingSessionFactory(
            static function (ConnectionInterface $connection, ZtdConfig $config) use (&$receivedConfig, $rewriter): Session {
                $receivedConfig = $config;

                return new Session($rewriter, new ShadowStore(), new ResultSelectRunner(), $config, $connection);
            },
        );

        new ZtdPdo('sqlite::memory:', null, null, null, $expectedConfig, $mockFactory);

        self::assertSame($expectedConfig, $receivedConfig);

        self::assertCount(1, $mockFactory->calls());
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
        $ztdPdo = $this->providerShadowedUsers();

        self::assertTrue($ztdPdo->isZtdEnabled());
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
        self::assertSame(ZtdPdo::class, ZtdPdo::connect('sqlite::memory:')::class);
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
        $ztdPdo = $this->providerShadowedUsers();

        self::assertFalse($ztdPdo->inTransaction());
    }

    public function testLastInsertIdAnswersTheKeyTheShadowGaveTheRowItWrote(): void
    {
        $ztdPdo = $this->providerShadowedUsers();
        $ztdPdo->exec("INSERT INTO users (name) VALUES ('linus')");

        self::assertSame('1', $ztdPdo->lastInsertId());
    }

    public function testErrorCodeAnswersWhatTheDriverSaysWentWrongLast(): void
    {
        $ztdPdo = $this->providerShadowedUsers();

        self::assertSame('00000', $ztdPdo->errorCode());
    }

    public function testErrorInfoAnswersWhatTheDriverSaysAboutTheLastFailure(): void
    {
        $ztdPdo = $this->providerShadowedUsers();

        self::assertSame('00000', $ztdPdo->errorInfo()[0]);
    }

    public function testGetAttributeReadsTheAttributeOffTheConnectionItWraps(): void
    {
        $ztdPdo = $this->providerShadowedUsers();

        self::assertSame('sqlite', $ztdPdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function testSetAttributeSetsTheAttributeOnTheConnectionItWraps(): void
    {
        $ztdPdo = $this->providerShadowedUsers();

        $ztdPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_NUM);

        self::assertSame(PDO::FETCH_NUM, $ztdPdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testQuoteWritesAValueTheWayTheDriverWouldQuoteIt(): void
    {
        $ztdPdo = $this->providerShadowedUsers();

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

        $ztdPdo = $this->providerShadowedUsers();

        $ztdPdo->pgsqlCopyToArray('users');
    }

    public function testCopyToArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $ztdPdo = $this->providerShadowedUsers();

        $ztdPdo->copyToArray('users');
    }

    public function testPgsqlCopyFromArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $ztdPdo = $this->providerShadowedUsers();

        $ztdPdo->pgsqlCopyFromArray('users', ["1\tada\n"]);
    }

    public function testCopyFromArrayRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $ztdPdo = $this->providerShadowedUsers();

        $ztdPdo->copyFromArray('users', ["1\tada\n"]);
    }

    public function testPgsqlCopyToFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $ztdPdo = $this->providerShadowedUsers();

        $ztdPdo->pgsqlCopyToFile('users', '/dev/null');
    }

    public function testCopyToFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $ztdPdo = $this->providerShadowedUsers();

        $ztdPdo->copyToFile('users', '/dev/null');
    }

    public function testPgsqlCopyFromFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $ztdPdo = $this->providerShadowedUsers();

        $ztdPdo->pgsqlCopyFromFile('users', '/dev/null');
    }

    public function testCopyFromFileRefusesADialectWithNoCopy(): void
    {
        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $ztdPdo = $this->providerShadowedUsers();

        $ztdPdo->copyFromFile('users', '/dev/null');
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
}
