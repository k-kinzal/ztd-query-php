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
final class ReplicationPortTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = [
            'CHANGE REPLICATION SOURCE TO SOURCE_PORT=0',
            "CHANGE REPLICATION SOURCE TO SOURCE_PORT=X'9043ac133F7D6F1fd654',SOURCE_SSL_CRLPATH='a''b',REQUIRE_TABLE_PRIMARY_KEY_CHECK=OFF",
            'CHANGE REPLICATION SOURCE TO SOURCE_PORT=2147483647',
            'CHANGE REPLICATION SOURCE TO SOURCE_PORT=2147483648',
            'CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0',
            'RESET REPLICA',
            'CHANGE REPLICATION SOURCE TO SOURCE_PORT=4294967296',
            'CHANGE REPLICATION SOURCE TO SOURCE_PORT=4294970602',
            'CHANGE REPLICATION SOURCE TO SOURCE_PORT=0',
            "CHANGE REPLICATION SOURCE TO SOURCE_PORT=X'9043ac133F7D6F1fd654',SOURCE_SSL_CRLPATH='a''b',REQUIRE_TABLE_PRIMARY_KEY_CHECK=OFF",
            'RESET REPLICA ALL',
            'CHANGE REPLICATION SOURCE TO SOURCE_PORT=0',
            'CHANGE REPLICATION SOURCE TO SOURCE_PORT=2147483648,SOURCE_AUTO_POSITION=1',
            'CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0',
            "CHANGE REPLICATION SOURCE TO SOURCE_PORT=2147483648,SOURCE_COMPRESSION_ALGORITHMS='bad'",
            'CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0',
            'CHANGE REPLICATION SOURCE TO SOURCE_PORT=3306',
            "CHANGE REPLICATION SOURCE TO SOURCE_PORT=2147483648,RELAY_LOG_FILE='missing'",
            'CHANGE REPLICATION SOURCE TO SOURCE_PORT=3306',
            'CHANGE REPLICATION SOURCE TO SOURCE_PORT=65536',
            'CHANGE REPLICATION SOURCE TO SOURCE_PORT=4294967295',
            'RESET REPLICA ALL',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Unsigned ports, failed metadata writes, reset and competing configuration errors
     */
    #[DataProvider('providerInputs')]
    public function testConnectionPortHistoryMatchesNative(string $release, array $statements): void
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
