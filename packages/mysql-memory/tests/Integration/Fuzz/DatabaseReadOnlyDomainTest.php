<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DatabaseReadOnlyDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d'];
        foreach (['DEFAULT', '0', '1', '00', '01', '0001', '2', '-1', '+1', '0.0', '1.0', '0.9', '1e0', '0e0', '0x0', '0x1', "x'01'", "b'1'", '4294967296', '4294967297', '18446744073709551615', '18446744073709551616'] as $value) {
            $statements[] = 'ALTER DATABASE d READ ONLY ' . $value;
            $statements[] = 'ALTER DATABASE d READ ONLY 0';
        }
        array_push($statements, "ALTER DATABASE `` READ ONLY DEFAULT READ ONLY 2 DEFAULT ENCRYPTION 't'", 'ALTER DATABASE d CHARACTER SET nope READ ONLY 2', 'ALTER DATABASE d READ ONLY 2 CHARACTER SET nope', "ALTER DATABASE d READ ONLY 1 DEFAULT ENCRYPTION 't'", 'ALTER DATABASE d READ ONLY 2 +', 'ALTER DATABASE d READ ONLY 2 CHARACTER SET utf8', 'ALTER DATABASE d CHARACTER SET utf8 READ ONLY 2', 'ALTER DATABASE d READ ONLY 2; SELECT 1', "ALTER DATABASE d READ ONLY 2\n ENCRYPTION 'N'");
        foreach ([
            'ALTER DATABASE d READ ONLY 0 READ ONLY 0',
            'ALTER DATABASE d READ ONLY DEFAULT READ ONLY 0',
            'ALTER DATABASE d READ ONLY 1 READ ONLY 1',
            'ALTER DATABASE d READ ONLY 0 READ ONLY 1',
            'ALTER DATABASE d READ ONLY 1 READ ONLY DEFAULT',
            'ALTER DATABASE d READ ONLY 0.9 READ ONLY 1e0',
            'ALTER DATABASE d READ ONLY 0 READ ONLY DEFAULT READ ONLY 1',
            'ALTER DATABASE d READ ONLY 0 READ ONLY 1 ENCRYPTION \'bad\'',
            'ALTER DATABASE d ENCRYPTION \'bad\' READ ONLY 0 READ ONLY 1',
            'ALTER DATABASE d READ ONLY 0 CHARACTER SET nope READ ONLY 1',
            'ALTER DATABASE d READ ONLY 0 READ ONLY 1 CHARACTER SET nope',
            'ALTER DATABASE d CHARACTER SET utf8 READ ONLY DEFAULT READ ONLY 1',
            'ALTER DATABASE d READ ONLY DEFAULT READ ONLY 1 CHARACTER SET utf8',
            'ALTER DATABASE d READ ONLY 0 READ ONLY 1 +',
            'ALTER DATABASE d READ ONLY 0 READ ONLY 1 READ ONLY 2',
            'ALTER DATABASE d READ ONLY 0 READ ONLY 2 READ ONLY 1',
            'ALTER DATABASE d READ ONLY 0 READ ONLY 1; SELECT 1',
            'ALTER DATABASE d READ ONLY 1 READ ONLY 0 READ ONLY 1',
            'ALTER DATABASE d CHARACTER SET latin1 CHARACTER SET utf8mb4 READ ONLY 0 READ ONLY 1',
            'ALTER DATABASE d READ ONLY 0 READ ONLY 1 CHARACTER SET latin1 CHARACTER SET utf8mb4',
            'ALTER DATABASE d COLLATE latin1_bin COLLATE utf8mb4_bin READ ONLY 0 READ ONLY 1',
            'ALTER DATABASE d CHARACTER SET latin1 COLLATE utf8mb4_bin READ ONLY 0 READ ONLY 1',
            'ALTER DATABASE d ENCRYPTION \'Y\' ENCRYPTION \'N\' READ ONLY 0 READ ONLY 1',
            'ALTER DATABASE d READ ONLY 0 ENCRYPTION \'Y\' ENCRYPTION \'N\' READ ONLY 1',
            'ALTER DATABASE d ENCRYPTION \'bad\' CHARACTER SET nope',
            'ALTER DATABASE d CHARACTER SET nope ENCRYPTION \'bad\'',
            'ALTER DATABASE d ENCRYPTION \'bad\' +',
            'ALTER DATABASE d CHARACTER SET latin1 CHARACTER SET utf8mb4 +',
            'ALTER DATABASE RELOAD READ ONLY DEFAULT READ ONLY DEFAULT READ ONLY = 1 READ ONLY 00000000000001 DEFAULT ENCRYPTION := \'a\'\'b\' READ ONLY 00000000001 DEFAULT COLLATE := BINARY',
] as $statement) {
            $statements[] = $statement;
            $statements[] = 'ALTER DATABASE d READ ONLY 0';
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The ordered option inputs and resets
     */
    #[DataProvider('providerReleases')]
    public function testOptionDomainAndDiagnosticOrderMatchMySql(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
