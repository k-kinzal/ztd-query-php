<?php

declare(strict_types=1);

namespace Tests\Unit\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\MySql\Connection\MySqlServerVersionGuard;

#[CoversClass(MySqlServerVersionGuard::class)]
final class MySqlServerVersionGuardTest extends TestCase
{
    #[DataProvider('providerSupportedVersions')]
    public function testValidateAcceptsSupportedServerVersions(string $version): void
    {
        $statement = self::createStub(StatementInterface::class);
        $statement->method('fetchAll')->willReturn([['ztd_server_version' => $version]]);
        $connection = self::createMock(ConnectionInterface::class);
        $connection->expects(self::once())->method('query')
            ->with('SELECT VERSION() AS ztd_server_version')->willReturn($statement);

        (new MySqlServerVersionGuard())->validate($connection);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSupportedVersions(): iterable
    {
        yield 'first GA release' => ['8.0.11'];
        yield 'server suffix' => ['8.0.11-log'];
        yield 'distribution suffix' => ['8.0.44-0ubuntu0.22.04.1'];
        yield '8.0 CI version' => ['8.0.44'];
        yield '8.1' => ['8.1.0'];
        yield '8.2' => ['8.2.0'];
        yield '8.3' => ['8.3.0'];
        yield '8.4 CI version' => ['8.4.7'];
        yield '9.0' => ['9.0.1'];
        yield '9.1' => ['9.1.0'];
    }

    #[DataProvider('providerUnsupportedVersions')]
    public function testValidateRejectsUnsupportedServerVersions(string $version): void
    {
        $statement = self::createStub(StatementInterface::class);
        $statement->method('fetchAll')->willReturn([['ztd_server_version' => $version]]);
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn($statement);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ZTD requires MySQL 8.0.11 or later with WITH (CTE) support; server reported "' . $version . '".');

        (new MySqlServerVersionGuard())->validate($connection);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnsupportedVersions(): iterable
    {
        yield 'MySQL 5.6' => ['5.6.51'];
        yield 'MySQL 5.7' => ['5.7.44'];
        yield 'old server with suffix' => ['5.7.44-log'];
        yield 'before CTE support' => ['8.0.0'];
        yield 'CTE development milestone' => ['8.0.1'];
        yield 'release candidate' => ['8.0.4-rc'];
        yield 'below minimum' => ['8.0.10'];
        yield 'MariaDB uses different version numbers' => ['10.1.48-MariaDB'];
        yield 'MariaDB compatibility prefix' => ['5.5.5-10.11.14-MariaDB'];
    }

    public function testValidateRejectsFailedVersionQuery(): void
    {
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to determine the MySQL server version');

        (new MySqlServerVersionGuard())->validate($connection);
    }

    #[DataProvider('providerInvalidVersionRows')]
    public function testValidateRejectsUnavailableOrMalformedVersions(?string $version): void
    {
        $statement = self::createStub(StatementInterface::class);
        $statement->method('fetchAll')->willReturn($version === null ? [] : [['ztd_server_version' => $version]]);
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn($statement);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to determine the MySQL server version');

        (new MySqlServerVersionGuard())->validate($connection);
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function providerInvalidVersionRows(): iterable
    {
        yield 'missing row' => [null];
        yield 'empty version' => [''];
        yield 'unrecognized version' => ['unknown'];
        yield 'missing patch version' => ['8.0'];
    }
}
