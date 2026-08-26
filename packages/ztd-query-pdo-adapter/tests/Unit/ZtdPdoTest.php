<?php

declare(strict_types=1);

namespace Tests\Unit;

use Override;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use ZtdQuery\Adapter\Pdo\PdoConnection;
use ZtdQuery\Adapter\Pdo\PdoStatement;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
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
final class ZtdPdoTest extends TestCase
{
    public function testConstructorUsesExplicitSessionFactory(): void
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

    public function testDriverMapContainsExpectedDrivers(): void
    {
        $reflection = new ReflectionClass(ZtdPdo::class);
        $raw = $reflection->getConstant('DRIVER_MAP');
        self::assertIsArray($raw);

        /** @var array<string, array{class: string, package: string}> $driverMap */
        $driverMap = $raw;

        self::assertArrayHasKey('mysql', $driverMap);
        self::assertArrayHasKey('pgsql', $driverMap);
        self::assertArrayHasKey('sqlite', $driverMap);

        self::assertSame('ZtdQuery\\Platform\\MySql\\MySqlSessionFactory', $driverMap['mysql']['class']);
        self::assertSame('k-kinzal/ztd-query-mysql', $driverMap['mysql']['package']);

        self::assertSame('ZtdQuery\\Platform\\Postgres\\PgSqlSessionFactory', $driverMap['pgsql']['class']);
        self::assertSame('k-kinzal/ztd-query-postgres', $driverMap['pgsql']['package']);

        self::assertSame('ZtdQuery\\Platform\\Sqlite\\SqliteSessionFactory', $driverMap['sqlite']['class']);
        self::assertSame('k-kinzal/ztd-query-sqlite', $driverMap['sqlite']['package']);
    }

    public function testUnsupportedDriverThrowsException(): void
    {
        $reflection = new ReflectionClass(ZtdPdo::class);
        $method = $reflection->getMethod('detectFactory');

        $fakePdo = new class ('sqlite::memory:') extends PDO {
            public string $fakeDriver = '';

            #[Override]
            public function getAttribute(int $attribute): mixed
            {
                if ($attribute === PDO::ATTR_DRIVER_NAME) {
                    return $this->fakeDriver;
                }

                return parent::getAttribute($attribute);
            }
        };
        $fakePdo->fakeDriver = 'oci';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported PDO driver: "oci"');

        $method->invoke(null, $fakePdo);
    }

    public function testUnsupportedDriverErrorListsSupportedDrivers(): void
    {
        $reflection = new ReflectionClass(ZtdPdo::class);
        $method = $reflection->getMethod('detectFactory');

        $fakePdo = new class ('sqlite::memory:') extends PDO {
            public string $fakeDriver = '';

            #[Override]
            public function getAttribute(int $attribute): mixed
            {
                if ($attribute === PDO::ATTR_DRIVER_NAME) {
                    return $this->fakeDriver;
                }

                return parent::getAttribute($attribute);
            }
        };
        $fakePdo->fakeDriver = 'firebird';

        try {
            $method->invoke(null, $fakePdo);
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('mysql', $e->getMessage());
            self::assertStringContainsString('pgsql', $e->getMessage());
            self::assertStringContainsString('sqlite', $e->getMessage());
        }
    }

    public function testDetectFactorySucceedsForInstalledDriver(): void
    {
        $reflection = new ReflectionClass(ZtdPdo::class);
        $method = $reflection->getMethod('detectFactory');

        $fakePdo = new class ('sqlite::memory:') extends PDO {
            public string $fakeDriver = '';

            #[Override]
            public function getAttribute(int $attribute): mixed
            {
                if ($attribute === PDO::ATTR_DRIVER_NAME) {
                    return $this->fakeDriver;
                }

                return parent::getAttribute($attribute);
            }
        };
        $fakePdo->fakeDriver = 'mysql';

        (fn () => class_exists('ZtdQuery\\Platform\\MySql\\MySqlSessionFactory') || self::markTestSkipped('ztd-query-mysql package is not installed.'))();

        $result = $method->invoke(null, $fakePdo);
        self::assertInstanceOf(SessionFactory::class, $result);
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

    public function testConstructorPassesExplicitConfigToFactory(): void
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

}
