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
final class DefaultEncodingInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = [
         'SET NAMES DEFAULT COLLATE utf8mb4_bin',
         'SELECT @@character_set_client,@@collation_connection',
         'SET GLOBAL character_set_server=utf8mb4',
         'SET NAMES DEFAULT',
         'SELECT @@character_set_client,@@collation_connection',
         'SET GLOBAL character_set_client=utf8mb4',
         'SET NAMES DEFAULT',
         'SELECT @@character_set_client,@@collation_connection',
         'SET NAMES latin1',
         'SET NAMES DEFAULT COLLATE latin1_bin',
         'SELECT @@character_set_client,@@collation_connection',
            'SET GLOBAL character_set_client=latin1',
            'SET NAMES DEFAULT COLLATE latin1_bin',
            'SET GLOBAL character_set_server=utf8mb4',
            'SET NAMES DEFAULT COLLATE latin1_bin',
            'SET GLOBAL character_set_client=utf8mb4',
            'SET NAMES DEFAULT COLLATE latin1_bin',
            'SET NAMES DEFAULT COLLATE utf8mb4_bin',
            'SET GLOBAL character_set_server=latin1',
            'SET NAMES DEFAULT COLLATE utf8mb4_bin',
            'SET GLOBAL character_set_client=latin1',
            'SET NAMES DEFAULT COLLATE utf8mb4_bin',
            'SET NAMES DEFAULT COLLATE latin1_bin',
            'SELECT @@character_set_client,@@collation_connection',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Changes to server and client globals around DEFAULT encoding assignments
     */
    #[DataProvider('providerStatements')]
    public function testDefaultUsesTheGlobalClientEncodingAcrossSessionHistory(string $release, array $statements): void
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
