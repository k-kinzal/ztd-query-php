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
final class DerivedOriginTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $queries = ['CREATE DATABASE d', 'CREATE TABLE d.t(v INT PRIMARY KEY, w INT)', 'INSERT INTO d.t VALUES(2,3),(4,3)'];
            foreach (['SELECT v,w FROM d.t LIMIT 1', 'SELECT DISTINCT w FROM d.t', 'SELECT w FROM d.t GROUP BY w', 'SELECT w,COUNT(*) c FROM d.t GROUP BY w', 'SELECT v,w FROM d.t UNION ALL SELECT v,w FROM d.t', 'SELECT v AS renamed FROM d.t LIMIT 1', 'SELECT v+0 AS expression FROM d.t LIMIT 1', 'SELECT 1 AS constant FROM d.t LIMIT 1'] as $body) {
                $queries[] = 'SELECT * FROM (' . $body . ') AS q';
            }
            yield $release => [$release, $queries];
        }
    }

    /**
     * @param list<string> $statements Materialized fields, computed fields and set operations
     */
    #[DataProvider('providerStatements')]
    public function testDerivedMaterializationPreservesSourceColumnMetadata(string $release, array $statements): void
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
