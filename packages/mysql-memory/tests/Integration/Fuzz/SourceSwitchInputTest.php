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
final class SourceSwitchInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, list<string>, array<string, string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [];
        foreach (['SOURCE_CONNECTION_AUTO_FAILOVER', 'GTID_ONLY', 'REQUIRE_ROW_FORMAT'] as $kind) {
            foreach (['0', '1', '2', '00', '01', '0.1', '1.0', '1e0', '0x0', '0x1', '0x100000000', '0x100000001', '4294967296', '4294967297', '18446744073709551615'] as $value) {
                $statements[] = 'CHANGE REPLICATION SOURCE TO ' . $kind . '=' . $value;
            }
            array_push($statements, 'CHANGE REPLICATION SOURCE TO ' . $kind . '=2 TRAILING', 'CHANGE REPLICATION SOURCE TO ' . $kind . "=2,SOURCE_HOST='x\\ny'", "CHANGE REPLICATION SOURCE TO SOURCE_HOST='x\\ny'," . $kind . '=2');
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . ': GTIDs off' => [$release, $statements, [], []];
            yield $release . ': configured channel' => [$release, [
                'CHANGE REPLICATION SOURCE TO GTID_ONLY=1',
                'CHANGE REPLICATION SOURCE TO SOURCE_AUTO_POSITION=1, REQUIRE_ROW_FORMAT=1',
                'CHANGE REPLICATION SOURCE TO GTID_ONLY=1',
                'CHANGE REPLICATION SOURCE TO REQUIRE_ROW_FORMAT=0',
                'CHANGE REPLICATION SOURCE TO SOURCE_AUTO_POSITION=0',
                'CHANGE REPLICATION SOURCE TO GTID_ONLY=0, REQUIRE_ROW_FORMAT=0, SOURCE_AUTO_POSITION=0',
                'CHANGE REPLICATION SOURCE TO SOURCE_CONNECTION_AUTO_FAILOVER=1',
                'RESET REPLICA ALL',
                'CHANGE REPLICATION SOURCE TO GTID_ONLY=1',
                'CHANGE REPLICATION SOURCE TO SOURCE_AUTO_POSITION=1, REQUIRE_ROW_FORMAT=1, GTID_ONLY=1',
                'RESET REPLICA',
                'CHANGE REPLICATION SOURCE TO GTID_ONLY=1',
                'RESET REPLICA ALL, REPLICA',
                'CHANGE REPLICATION SOURCE TO GTID_ONLY=1',
                'RESET REPLICA, REPLICA ALL',
                'CHANGE REPLICATION SOURCE TO GTID_ONLY=1',
            ], ['SET GLOBAL enforce_gtid_consistency=ON', 'SET GLOBAL gtid_mode=OFF_PERMISSIVE', 'SET GLOBAL gtid_mode=ON_PERMISSIVE', 'SET GLOBAL gtid_mode=ON'], ['gtid_mode' => 'ON', 'enforce_gtid_consistency' => 'ON']];
        }
    }

    /**
     * @param list<string> $statements Source switch domains, prerequisites and ordered input refusals
     * @param list<string> $setup Statements configuring the reference server's initial state
     * @param array<string, string> $globals The same initial state for memory
     */
    #[DataProvider('providerReleases')]
    public function testSourceSwitchesMatchNative(string $release, array $statements, array $setup, array $globals): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            array_map(static fn (string $sql): int|false => $native->exec($sql), $setup);
            $server = Server::start($release, [], $globals);
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
