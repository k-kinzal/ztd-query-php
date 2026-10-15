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
final class ReplicationVocabularyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = [
            'START SLAVE UNTIL MASTER_LOG_POS=1',
            "START SLAVE UNTIL MASTER_LOG_FILE='f', MASTER_LOG_POS=1",
            'START REPLICA UNTIL MASTER_LOG_POS=1',
            'START SLAVE UNTIL RELAY_LOG_POS=1',
            'START SLAVE SQL_THREAD USER=\'u\'',
            'START SLAVE FOR CHANNEL \'missing\'',
            'STOP SLAVE',
            'STOP SLAVE FOR CHANNEL \'missing\'',
            'SHOW SLAVE STATUS',
            'SHOW SLAVE HOSTS',
            'RESET SLAVE, SLAVE',
            'RESET SLAVE FOR CHANNEL \'missing\'',
            'CHANGE REPLICATION SOURCE TO MASTER_COMPRESSION_ALGORITHMS=\'zlib\'',
            'CHANGE MASTER TO SOURCE_COMPRESSION_ALGORITHMS=\'zlib\'',
            'CHANGE MASTER TO MASTER_HEARTBEAT_PERIOD=4294967.5',
            'CHANGE MASTER TO MASTER_COMPRESSION_ALGORITHMS=\'text\', MASTER_HEARTBEAT_PERIOD=4294967.5',
        ];
        foreach (['8.0.44', '8.1.0', '8.2.0', '8.3.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Legacy vocabulary with both successful and refused operands
     */
    #[DataProvider('providerInputs')]
    public function testSemanticNoticesMatchNativeBeforeAndDuringExecution(string $release, array $statements): void
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
