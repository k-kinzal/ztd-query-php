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
final class StoredViewCteTextTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(x INT,y INT)', 'INSERT INTO t VALUES(5,6)'];
        foreach ([
            'TABLE t',
            'WITH a AS (TABLE t) TABLE a',
            'WITH a AS (TABLE t), b AS (TABLE a) TABLE a',
            'WITH a AS (TABLE t), b AS (TABLE a) TABLE b',
            'WITH a AS (SELECT 1), b AS (TABLE t) TABLE b',
            'WITH a AS (TABLE t) SELECT 1 AS x',
            'WITH a AS (TABLE t), unused AS (SELECT 1), c AS (TABLE a) TABLE c',
            'WITH a AS (TABLE t), unused AS (SELECT 1) SELECT x FROM a GROUP BY x WITH ROLLUP',
            'WITH a AS (TABLE t), unused AS (SELECT 1) SELECT * FROM a NATURAL JOIN t',
            'WITH a AS (TABLE t), unused AS (SELECT 1) SELECT * FROM a JOIN t USING(x,y)',
            'WITH a AS (TABLE t), unused AS (SELECT 1) SELECT ROW_NUMBER() OVER () AS n FROM a',
            'WITH a AS (TABLE t), unused AS (SELECT 1) TABLE a UNION TABLE a',
            'SELECT * FROM (WITH a AS (TABLE t), unused AS (SELECT 1) TABLE a) q',
            'WITH a AS (SELECT x FROM t), unused AS (SELECT 1) SELECT (TABLE a) AS z',
            'WITH a AS (TABLE t), unused AS (SELECT 1) SELECT CAST(x AS CHAR) AS z FROM a',
            'SELECT t.x FROM t NATURAL RIGHT JOIN t u',
        ] as $index => $body) {
            $statements[] = 'CREATE VIEW v' . $index . ' AS ' . $body;
            $statements[] = 'SELECT * FROM v' . $index;
            $statements[] = 'SHOW CREATE VIEW v' . $index;
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Stored view dependencies, declaration text and reopening conditions
     */
    #[DataProvider('providerInputs')]
    public function testStoredViewBodiesAndReopeningConditionsMatchNativeQueries(string $release, array $statements): void
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
