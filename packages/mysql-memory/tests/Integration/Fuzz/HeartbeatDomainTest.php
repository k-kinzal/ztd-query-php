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
final class HeartbeatDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.1.0'] as $release) {
            $legacy = str_starts_with($release, '5.');
            $source = $legacy ? 'CHANGE MASTER TO MASTER_' : 'CHANGE REPLICATION SOURCE TO SOURCE_';
            $statements = [];
            foreach (['0', '4294967', '4294967.5', '2147483648', '18446744073709551616', '1e300'] as $number) {
                $statements[] = $source . 'HEARTBEAT_PERIOD=' . $number;
            }
            $statements[] = $source . 'HEARTBEAT_PERIOD=4294967.5,INVALID';
            $statements[] = $source . 'HEARTBEAT_PERIOD=4294967.5,' . ($legacy ? 'MASTER_' : 'SOURCE_') . "HOST='a\nb'";
            if (!$legacy && version_compare($release, '8.4', '<')) {
                $statements[] = "CHANGE MASTER TO MASTER_COMPRESSION_ALGORITHMS='text'";
                $statements[] = "CHANGE MASTER TO MASTER_HEARTBEAT_PERIOD=4294967.5,MASTER_COMPRESSION_ALGORITHMS='text'";
                $statements[] = "CHANGE MASTER TO MASTER_COMPRESSION_ALGORITHMS='text',MASTER_HEARTBEAT_PERIOD=4294967.5";
                $statements[] = 'CHANGE MASTER TO SOURCE_HEARTBEAT_PERIOD=4294967.5';
            }
            $prefix = $legacy ? 'MASTER_' : 'SOURCE_';
            $timeout = $legacy ? 'slave_net_timeout' : 'replica_net_timeout';
            $channel = $release === '5.6.51' ? ',INVALID' : " FOR CHANNEL '\n'";
            foreach (['0.0001', '100', '100, ' . $prefix . 'HEARTBEAT_PERIOD=0', '100, ' . $prefix . 'HEARTBEAT_PERIOD=100'] as $period) {
                $statements[] = $source . 'HEARTBEAT_PERIOD=' . $period . $channel;
            }
            $statements[] = 'SET GLOBAL ' . $timeout . '=200';
            $statements[] = $source . 'HEARTBEAT_PERIOD=100' . $channel;
            $statements[] = 'SET GLOBAL ' . $timeout . '=10';
            $statements[] = $source . 'HEARTBEAT_PERIOD=100' . $channel;
            if (version_compare($release, '8.4', '>=')) {
                $statements[] = 'SHOW PARSE_TREE ' . $source . 'HEARTBEAT_PERIOD=100' . $channel;
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Heartbeat boundaries, competing operand failures and deprecated replication vocabulary
     */
    #[DataProvider('providerInputs')]
    public function testHeartbeatInputDomainAndVocabularyMatchNative(string $release, array $statements): void
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
