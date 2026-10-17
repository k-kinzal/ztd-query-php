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
final class ViewQueryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE DATABASE view_queries CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
            'USE view_queries',
            'CREATE TABLE t(a INT)',
            'ALTER VIEW absent_db.v AS SELECT * FROM absent_table',
            'ALTER VIEW absent_db.v AS SELECT absent_column',
            'ALTER VIEW absent_db.v AS SELECT absent_function()',
            'ALTER VIEW t AS SELECT * FROM absent_table',
            'ALTER VIEW t AS SELECT absent_column',
            'ALTER VIEW t AS SELECT absent_function()',
            'ALTER VIEW absent_db.v AS SELECT absent_function() FROM absent_table',
            'ALTER VIEW absent_db.v AS SELECT absent_column FROM absent_table',
            'ALTER VIEW absent_db.v AS SELECT * FROM t WHERE absent_column',
            'ALTER VIEW absent_db.v AS SELECT * FROM t ORDER BY absent_column',
            'ALTER VIEW absent_db.v(a, b) AS SELECT absent_column',
            'ALTER VIEW absent_db.v(a, a) AS SELECT 1, absent_column',
            'CREATE VIEW v AS SELECT 1 WHERE TRUE',
            'ALTER VIEW v AS SELECT 2 WHERE TRUE',
            'SELECT * FROM v',
            'CREATE VIEW short_value AS SELECT 1 a',
            'SELECT * FROM short_value',
            'CREATE VIEW widths AS SELECT 0 a, -1 b, 12345678901 c, CAST(7 AS SIGNED) d',
            'SELECT * FROM widths',
            'CREATE VIEW nested AS SELECT * FROM widths',
            'SELECT * FROM nested',
            'INSERT INTO t VALUES (1)',
            'CREATE VIEW merged AS SELECT 1 a FROM t',
            'SELECT * FROM merged',
            'CREATE ALGORITHM = TEMPTABLE VIEW forced AS SELECT 1 a FROM t',
            'SELECT * FROM forced',
            "CREATE VIEW kinds AS SELECT 'abc' a, 1.25 b, CAST('2020-01-01' AS DATE) c",
            'SELECT * FROM kinds',
            'CREATE VIEW null_value AS SELECT NULL a',
            'SELECT * FROM null_value',
            'SHOW COLUMNS FROM null_value',
            'CREATE VIEW rebound AS SELECT 1 n, a FROM t LIMIT 1',
            'SELECT * FROM rebound',
            'DROP TABLE t',
            'CREATE TABLE t(a VARCHAR(12))',
            "INSERT INTO t VALUES ('x')",
            'SELECT * FROM rebound',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The view query history to compare
     */
    #[DataProvider('providerReleases')]
    public function testViewQueryDiagnosticsMatchNative(string $release, array $statements): void
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
