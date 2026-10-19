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
final class ReplicationRetryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = [(string) file_get_contents(__DIR__ . '/fixtures/replication-retry-prefix.sql')];
        $tails = ['SOURCE_CONNECT_RETRY=0', 'SOURCE_CONNECT_RETRY=4294967296', 'SOURCE_PORT=3307', 'SOURCE_CONNECT_RETRY=1', 'SOURCE_CONNECT_RETRY=4294967297', 'SOURCE_PORT=2147483648', 'SOURCE_PORT=2147483648,SOURCE_CONNECT_RETRY=2147483648', 'SOURCE_CONNECT_RETRY=2147483648,SOURCE_PORT=2147483648'];
        foreach ($tails as $tail) {
            array_push($statements, 'RESET REPLICA ALL', 'CHANGE REPLICATION SOURCE TO SOURCE_CONNECT_RETRY=2147483648', 'CHANGE REPLICATION SOURCE TO ' . $tail, 'RESET REPLICA', 'CHANGE REPLICATION SOURCE TO SOURCE_CONNECT_RETRY=1', 'RESET REPLICA ALL', 'CHANGE REPLICATION SOURCE TO SOURCE_CONNECT_RETRY=1');
        }
        foreach (['0', '1', '2147483647', '2147483648', '4294967295', '4294967296', '9223372036854775807', '18446744073709551615', '1e2', '1.9e2', '.9e89', '2147483648.0', '4294967297.2', '18446744073709551616.0'] as $value) {
            array_push($statements, 'RESET REPLICA ALL', 'CHANGE REPLICATION SOURCE TO SOURCE_CONNECT_RETRY=' . $value, 'CHANGE REPLICATION SOURCE TO SOURCE_CONNECT_RETRY=1');
        }
        foreach (['1e2', '1.9e2', '.9e89', '2147483648.0', '4294967297.2', '18446744073709551616.0'] as $value) {
            array_push($statements, 'RESET REPLICA ALL', 'CHANGE REPLICATION SOURCE TO SOURCE_PORT=' . $value, 'CHANGE REPLICATION SOURCE TO SOURCE_PORT=3306');
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Unsigned retry intervals, failed metadata writes, reset and competing ports
     */
    #[DataProvider('providerInputs')]
    public function testConnectionRetryHistoryMatchesNative(string $release, array $statements): void
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
