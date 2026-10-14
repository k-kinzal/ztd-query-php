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
final class ResourceGroupNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [];
        foreach (['', ' ', str_repeat('a', 64), str_repeat('a', 65), str_repeat('猫', 21), str_repeat('猫', 22), str_repeat('é', 32), str_repeat('é', 33), str_repeat('😀', 64), str_repeat('😀', 65), ' group ', "zero\0tail"] as $name) {
            $quoted = '`' . $name . '`';
            array_push($statements, 'CREATE RESOURCE GROUP ' . $quoted . ' TYPE USER', 'ALTER RESOURCE GROUP ' . $quoted, 'SET RESOURCE GROUP ' . $quoted, 'SET RESOURCE GROUP USR_default', 'DROP RESOURCE GROUP ' . $quoted);
        }
        $long = '`' . str_repeat('a', 65) . '`';
        array_push($statements, 'CREATE RESOURCE GROUP ' . $long . ' TYPE USER VCPU 99999', 'ALTER RESOURCE GROUP ' . $long . ' VCPU 99999', 'SET RESOURCE GROUP ' . $long . ' FOR 99999', 'DROP RESOURCE GROUP ' . $long . ' nonsense', 'CREATE RESOURCE GROUP ' . $long . ' nonsense');
        array_push($statements, 'DROP RESOURCE GROUP `😀` nonsense', 'CREATE RESOURCE GROUP `😀` nonsense', 'ALTER RESOURCE GROUP `😀` THREAD_PRIORITY', 'SET RESOURCE GROUP `😀` FOR', 'CREATE RESOURCE GROUP `😀` TYPE USER VCPU 9999999999999999999999', 'DROP RESOURCE GROUP `' . str_repeat('😀', 65) . '` nonsense');
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . ':nul' => [$release, ["SELECT 'zero\0tail'", "SELECT 1 AS `zero\0tail`", "SET sql_mode='ANSI_QUOTES'", "SELECT 1 AS \"zero\0tail\"", "SELECT 'valid\0string'", "SELECT zero FROM (SELECT 'zero\0tail') t", "SELECT 1 AS 'zero\0tail'", "SELECT zero FROM (SELECT 1 AS 'zero\0tail') t", "SELECT _binary 'zero\0tail'", "SELECT 1 AS '\0zero'", "SELECT 1 AS ' zero\0tail'", "SELECT _ucs2 '\0z\0\0\0b'", "SELECT _utf16 '\0z\0\0\0b'", "SELECT _utf16le 'z\0\0\0b\0'", "SELECT _utf32 '\0\0\0z\0\0\0\0\0\0\0b'"]];
        }
    }

    /**
     * @param list<string> $statements The command histories and competing operand failures
     */
    #[DataProvider('providerReleases')]
    public function testNameDomainsAndCommandHistoriesMatchMySql(string $release, array $statements): void
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
