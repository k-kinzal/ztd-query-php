<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Adapter\Pdo\Session\DriverPlatform;

#[CoversClass(DriverPlatform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdoException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdo::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Driver\PdoConnection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Driver\PdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\StatementExecution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\Bindings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\BufferedRow::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\PreparedQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ConnectionExecution::class)]
final class DriverPlatformTest extends TestCase
{
    public function testDriverNamesAnswersEveryDriverZtdHasAPlatformFor(): void
    {
        self::assertSame(['mysql', 'pgsql', 'sqlite'], (new DriverPlatform())->driverNames());
    }

    public function testForDriverAnswersThePlatformThatDriverSpeaks(): void
    {
        self::assertInstanceOf(
            'ZtdQuery\\Platform\\Sqlite\\SqlitePlatform',
            (new DriverPlatform())->forDriver('sqlite'),
        );
    }

    public function testForDriverRefusesADriverZtdHasNoPlatformFor(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported PDO driver: "oci"');

        (new DriverPlatform())->forDriver('oci');
    }

    public function testForDriverNamesEverySupportedDriverWhenItRefusesOne(): void
    {
        $this->expectExceptionMessage('Supported drivers: mysql, pgsql, sqlite.');

        (new DriverPlatform())->forDriver('firebird');
    }

    public function testForConnectionReadsTheDriverOffTheConnection(): void
    {
        $platform = (new DriverPlatform())->forConnection(new PDO('sqlite::memory:'));

        self::assertInstanceOf('ZtdQuery\\Platform\\Sqlite\\SqlitePlatform', $platform);
    }


}
