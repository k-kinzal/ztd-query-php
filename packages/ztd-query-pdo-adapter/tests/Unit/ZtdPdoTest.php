<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use ZtdQuery\Adapter\Pdo\PdoConnection;
use ZtdQuery\Adapter\Pdo\PdoStatement;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ZtdPdo::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(PdoStatement::class)]
final class ZtdPdoTest extends TestCase
{
    /**
     * Test that an explicitly provided SessionFactory overrides auto-detection in constructor.
     */
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

    /**
     * Test that an explicitly provided SessionFactory overrides auto-detection in fromPdo().
     */
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

    /**
     * Test that auto-detection works for SQLite driver when the package is installed.
     */
    public function testAutoDetectionForSqliteDriver(): void
    {
        (fn () => class_exists('ZtdQuery\\Platform\\Sqlite\\SqliteSessionFactory') || self::markTestSkipped('ztd-query-sqlite package is not installed.'))();

        $pdo = new PDO('sqlite::memory:');
        $ztdPdo = ZtdPdo::fromPdo($pdo);

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    /**
     * Test that the DRIVER_MAP constant contains expected drivers.
     */
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

    /**
     * Test that unsupported driver throws RuntimeException with descriptive message.
     */
    public function testUnsupportedDriverThrowsException(): void
    {
        $reflection = new ReflectionClass(ZtdPdo::class);
        $method = $reflection->getMethod('detectFactory');

        $fakePdo = new class ('sqlite::memory:') extends PDO {
            public string $fakeDriver = '';

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

    /**
     * Test that the error message for unsupported driver lists all supported drivers.
     */
    public function testUnsupportedDriverErrorListsSupportedDrivers(): void
    {
        $reflection = new ReflectionClass(ZtdPdo::class);
        $method = $reflection->getMethod('detectFactory');

        $fakePdo = new class ('sqlite::memory:') extends PDO {
            public string $fakeDriver = '';

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

    /**
     * Test that a known driver with available class succeeds in auto-detection.
     */
    public function testDetectFactorySucceedsForInstalledDriver(): void
    {
        $reflection = new ReflectionClass(ZtdPdo::class);
        $method = $reflection->getMethod('detectFactory');

        $fakePdo = new class ('sqlite::memory:') extends PDO {
            public string $fakeDriver = '';

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

    /**
     * Test that enableZtd/disableZtd/isZtdEnabled work with explicit factory.
     */
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

    /**
     * Test that SessionFactory is called exactly once per ZtdPdo instance.
     */
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

    /**
     * Test that explicit config is passed to the factory via fromPdo.
     */
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

    /**
     * Test that explicit config is passed to the factory via constructor.
     */
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
