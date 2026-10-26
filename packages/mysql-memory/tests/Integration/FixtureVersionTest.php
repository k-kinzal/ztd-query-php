<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fuzz\Fixture\Servers;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversNothing]
#[Large]
final class FixtureVersionTest extends TestCase
{
    #[DataProvider('providerMatchingVersions')]
    public function testAMatchingReferenceHandshakeIsAccepted(string $version): void
    {
        $server = MemoryDatabase::start($version);
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            (new Servers())->assertVersion($connection, $version);

            self::assertSame($version, $connection->getAttribute(PDO::ATTR_SERVER_VERSION));
        } finally {
            $server->stop();
        }
    }

    #[DataProvider('providerMismatchedVersions')]
    public function testAMismatchedReferenceHandshakeIsRejected(string $actual, string $expected): void
    {
        $server = MemoryDatabase::start($actual);
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            self::assertSame($actual, $connection->getAttribute(PDO::ATTR_SERVER_VERSION));
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('expected ' . $expected . ', received');

            (new Servers())->assertVersion($connection, $expected);
        } finally {
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerMatchingVersions(): iterable
    {
        foreach (['5.6.51', '8.4.7', '9.1.0'] as $version) {
            yield $version => [$version];
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerMismatchedVersions(): iterable
    {
        foreach (['5.6.51', '8.4.7', '9.1.0'] as $version) {
            yield $version . ' differs' => [$version, '8.0.44'];
        }
        yield 'patch prefix differs' => ['8.4.7', '8.4.70'];
    }

    #[RunInSeparateProcess]
    public function testAMismatchedReferenceIsRejectedBeforeFixtureCleanup(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $connection->exec("CREATE USER 'version_sentinel'@'%'");
            $connection->exec("ALTER USER CURRENT_USER IDENTIFIED BY 'fixture'");
            putenv('MYSQL_VERSION=8.0.44');
            putenv('MYSQL_MEMORY_NATIVE_DSN=' . $server->dsn());
            putenv('MYSQL_MEMORY_NATIVE_USER=root');
            putenv('MYSQL_MEMORY_NATIVE_PASSWORD=fixture');
            try {
                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('expected 8.0.44, received');

                (new Servers())->start();
            } finally {
                $sentinel = $connection->query("SELECT User FROM mysql.user WHERE User = 'version_sentinel'");
                self::assertNotFalse($sentinel);
                self::assertSame([['version_sentinel']], $sentinel->fetchAll(PDO::FETCH_NUM));
            }
        } finally {
            $server->stop();
        }
    }
}
