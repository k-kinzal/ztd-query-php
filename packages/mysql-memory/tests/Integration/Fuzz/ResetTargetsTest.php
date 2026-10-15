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
final class ResetTargetsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $cases = [
            "REPLICA ALL FOR CHANNEL 'missing', REPLICA ALL",
            "REPLICA ALL, REPLICA ALL FOR CHANNEL 'missing'",
            "REPLICA FOR CHANNEL 'missing', REPLICA",
            'BINARY LOGS AND GTIDS TO 7, BINARY LOGS AND GTIDS',
            'BINARY LOGS AND GTIDS, BINARY LOGS AND GTIDS TO 7',
            'BINARY LOGS AND GTIDS TO 7, BINARY LOGS AND GTIDS TO 9',
            "REPLICA ALL FOR CHANNEL 'missing', BINARY LOGS AND GTIDS TO 9",
            "BINARY LOGS AND GTIDS TO 9, REPLICA ALL FOR CHANNEL 'missing'",
            "REPLICA ALL FOR CHANNEL 'missing', BINARY LOGS AND GTIDS TO 9, REPLICA ALL",
            'BINARY LOGS AND GTIDS TO 0, BINARY LOGS AND GTIDS TO 9',
            'BINARY LOGS AND GTIDS TO 9, BINARY LOGS AND GTIDS TO 0',
            "REPLICA FOR CHANNEL 'a\nb', REPLICA",
            "REPLICA FOR CHANNEL 'a\nb', BINARY LOGS AND GTIDS TO 0",
            "BINARY LOGS AND GTIDS TO 0, REPLICA FOR CHANNEL 'a\nb'",
            'BINARY LOGS AND GTIDS TO 1.5, REPLICA',
            'BINARY LOGS AND GTIDS TO 2000000001, REPLICA',
            'BINARY LOGS AND GTIDS TO 0, QUERY CACHE',
            'BINARY LOGS AND GTIDS TO 1.5, TRAILING',
            'BINARY LOGS AND GTIDS TO 2000000001, TRAILING',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = [];
            foreach ($cases as $case) {
                array_push($statements, 'RESET BINARY LOGS AND GTIDS TO 1', 'RESET ' . $case, 'SHOW BINARY LOGS');
            }
            yield $release => [$release, $release === '8.0.44' ? array_map(static fn (string $sql): string => str_replace('BINARY LOGS AND GTIDS', 'MASTER', $sql), $statements) : $statements];
        }
    }

    /**
     * @param list<string> $statements Repeated targets, observable log state and input refusals
     */
    #[DataProvider('providerReleases')]
    public function testResetTargetsAndRefusalsMatchNative(string $release, array $statements): void
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
