<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fixtures\DriverNamePdo;
use ZtdQuery\Adapter\Pdo\DriverSessionFactory;

#[CoversClass(DriverSessionFactory::class)]
final class DriverSessionFactoryTest extends TestCase
{
    public function testDriverNamesAnswersEveryDriverZtdHasAPlatformFor(): void
    {
        self::assertSame(['mysql', 'pgsql', 'sqlite'], (new DriverSessionFactory())->driverNames());
    }

    public function testForDriverAnswersThePlatformThatDriverSpeaks(): void
    {
        self::assertInstanceOf(
            'ZtdQuery\\Platform\\Sqlite\\SqliteSessionFactory',
            (new DriverSessionFactory())->forDriver('sqlite'),
        );
    }

    public function testForDriverRefusesADriverZtdHasNoPlatformFor(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported PDO driver: "oci"');

        (new DriverSessionFactory())->forDriver('oci');
    }

    public function testForDriverNamesEverySupportedDriverWhenItRefusesOne(): void
    {
        $this->expectExceptionMessage('Supported drivers: mysql, pgsql, sqlite.');

        (new DriverSessionFactory())->forDriver('firebird');
    }

    public function testForConnectionReadsTheDriverOffTheConnection(): void
    {
        $factory = (new DriverSessionFactory())->forConnection(new DriverNamePdo('mysql'));

        self::assertInstanceOf('ZtdQuery\\Platform\\MySql\\MySqlSessionFactory', $factory);
    }

    public function testForConnectionRefusesAConnectionThatNamesNoDriverZtdKnows(): void
    {
        $this->expectException(RuntimeException::class);

        (new DriverSessionFactory())->forConnection(new DriverNamePdo('oci'));
    }
}
