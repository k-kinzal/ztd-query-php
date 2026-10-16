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
final class LegacyTableStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $queries = ['SHOW TABLE STATUS', "SHOW TABLE STATUS LIKE 'x'", "SHOW TABLE STATUS WHERE Name = 'x'", 'SHOW TABLE STATUS WHERE 0', 'SHOW TABLE STATUS WHERE Rows = 0', 'SHOW TABLE STATUS WHERE Engine IS NULL'];
        $conditions = ['0', '1', 'NULL', 'TRUE', 'FALSE', "Name = 'x'", 'Name IS NULL', 'Name IS NOT NULL', "Engine = 'x'", 'Engine IS NULL', 'Engine IS NOT NULL', '`Rows` IS NULL', '`Rows` IS NOT NULL', '`Rows` = 0', 'Version IS NULL', 'Version = 0', 'Version = 10', 'Comment IS NULL', "Comment = 'x'", 'Create_time IS NULL', 'Update_time IS NULL', "Collation = 'x'", 'Collation IS NULL', '0 AND `Rows` = 0', '1 OR `Rows`=0', 'RAND()=0', '2=1', '2=2', 'NOT 1', 'NOT Engine IS NULL'];
        $statements = ['SHOW TABLE STATUS', 'CREATE DATABASE d', 'USE d', ...$queries, 'SET NAMES utf8mb4', ...$queries, 'SET NAMES latin1', ...$queries, 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(1),(2)', 'SHOW TABLE STATUS WHERE 0', "SHOW TABLE STATUS LIKE 'missing'", 'DROP TABLE t', 'SHOW TABLE STATUS', ...array_map(static fn (string $condition): string => 'SHOW TABLE STATUS WHERE ' . $condition, $conditions)];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Empty listings and filtered populated histories with complete result metadata
     */
    #[DataProvider('providerStatements')]
    public function testTableStatusPreservesReleaseSpecificMetadata(string $release, array $statements): void
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
