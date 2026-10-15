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
final class ExplainNumberTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerNumbers(): iterable
    {
        $numbers = ['0', '000255', '0xFF', "x'FFFF'", '4294967295', '4294967296', '4294967551', '9223372036854775807', '9223372036854775808', '18446744073709551615', '0x7FFFFFFFFFFFFFFF', '0x8000000000000000', '0xFFFFFFFFFFFFFFFF', '0x10000000000000000', '1.5', '1e0', '18446744073709551616'];
        $statements = ['SET NAMES utf8mb4'];
        foreach (['EXPLAIN', 'EXPLAIN FORMAT=JSON', 'EXPLAIN FORMAT=bad'] as $prefix) {
            foreach ($numbers as $number) {
                $statements[] = $prefix . ' FOR CONNECTION ' . $number;
            }
        }
        array_push($statements, 'EXPLAIN FOR CONNECTION 1.5 TRAILING', 'EXPLAIN FORMAT=bad FOR CONNECTION 1.5 TRAILING', 'CREATE DATABASE d', 'CREATE PROCEDURE d.p() EXPLAIN FOR CONNECTION 1.5');
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The accepted numeric conversions and refused token domains
     */
    #[DataProvider('providerNumbers')]
    public function testConnectionNumbersMatchNative(string $release, array $statements): void
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
