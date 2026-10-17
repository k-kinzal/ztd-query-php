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
final class IdentifierEncodingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerConnections(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.4.7'] as $release) {
            foreach (['latin1', 'utf8mb4'] as $charset) {
                yield $release . ':' . $charset => [$release, $charset];
            }
        }
    }

    #[DataProvider('providerConnections')]
    public function testIdentifiersRetainTheirMeaningAcrossConnectionCharsetChanges(string $release, string $charset): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port . ';charset=' . $charset, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn() . ';charset=' . $charset, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $sql = ['SELECT @@character_set_client, @@character_set_results, @@collation_connection', 'CREATE DATABASE d', 'USE d', 'CREATE TABLE `é` (`é` INT)', 'INSERT INTO `é` VALUES (7)', "SELECT `é`+1, `é`, 'é', 1 AS 'é\\0z' FROM `é`", 'SET NAMES utf8mb4', 'SELECT `Ã©` FROM `Ã©`', 'SELECT `é` FROM `é`'];
                $observer = new Observer();
                $expected = array_map(static fn (string $query): string => serialize($observer->observe($native, $query, true)), $sql);
                $actual = array_map(static fn (string $query): string => serialize($observer->observe($memory, $query, true)), $sql);
                self::assertSame($expected, $actual, $release . ':' . $charset);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
