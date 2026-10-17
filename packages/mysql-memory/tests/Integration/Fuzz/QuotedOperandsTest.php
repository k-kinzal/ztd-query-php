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
final class QuotedOperandsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'SHOW SLAVE STATUS FOR CHANNEL 0x41',
            'SHOW REPLICA STATUS FOR CHANNEL 0x41',
            'SHOW BINLOG EVENTS IN 0x41',
            'SHOW RELAYLOG EVENTS IN 0x41',
            'SHOW RELAYLOG EVENTS FOR CHANNEL 0x41',
            'BINLOG 0x41',
            'SHOW PROCEDURE STATUS LIKE 0x41',
            'INSTALL PLUGIN p SONAME 0x41',
            'PURGE BINARY LOGS TO 0x41',
            'CHANGE REPLICATION FILTER REPLICATE_WILD_DO_TABLE = (0x41)',
            'CHANGE REPLICATION FILTER REPLICATE_DO_DB = (d) FOR CHANNEL 0x41',
            'STOP SLAVE FOR CHANNEL 0x41',
            'STOP REPLICA FOR CHANNEL 0x41',
            'START SLAVE USER = 0x41',
            'START REPLICA PASSWORD = 0x41',
            'START REPLICA DEFAULT_AUTH = 0x41',
            'START REPLICA PLUGIN_DIR = 0x41',
            'START REPLICA FOR CHANNEL 0x41',
            'START SLAVE UNTIL SQL_BEFORE_GTIDS = 0x41',
            'RESET MASTER TO 0, QUERY CACHE',
            'CREATE UNDO TABLESPACE u ADD DATAFILE 0x41',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The quoted-only literal position regressions
     */
    #[DataProvider('providerReleases')]
    public function testQuotedOperandRefusalsMatchTheNativeServer(string $release, array $statements): void
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
