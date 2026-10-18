<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class CommonTablePreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'WITH c AS (SELECT ROW(1,2), missing()) SELECT 1',
            'WITH c AS (SELECT ROW(1,2), missing()) SELECT 1 FROM c',
            'WITH c AS (SELECT ROW(1,2), missing()) SELECT unknown FROM c',
            'WITH c AS (SELECT ROW(1,2), missing()) TABLE c',
            'CREATE DATABASE d',
            'USE d',
            'CREATE TABLE t(a INT)',
            'INSERT INTO t VALUES(3),(5)',
            'WITH c AS (SELECT ROW(1,2) LIKE 1) SELECT 1',
            'WITH c AS (SELECT ROW(1,2) LIKE 1) SELECT 1 FROM c',
            'WITH c AS (SELECT ROW(1,2) LIKE 1) SELECT unknown FROM c',
            'WITH c AS (SELECT ROW(1,2) LIKE 1) TABLE c',
            'WITH c AS (SELECT ROW(a,2) LIKE 1 FROM t) SELECT 1',
            'WITH c AS (SELECT ROW(a,2) LIKE 1 FROM t) SELECT 1 FROM c',
            'WITH c AS (SELECT ROW(a,2) LIKE 1 FROM t) SELECT unknown FROM c',
            'WITH c AS (SELECT ROW(a,2) LIKE 1 FROM t) TABLE c',
            'WITH c AS (SELECT ROW(b,2) LIKE 1 FROM t) SELECT 1',
            'WITH c AS (SELECT ROW(b,2) LIKE 1 FROM t) SELECT 1 FROM c',
            'WITH c AS (SELECT ROW(b,2) LIKE 1 FROM t) SELECT unknown FROM c',
            'WITH c AS (SELECT ROW(b,2) LIKE 1 FROM t) TABLE c',
            'WITH c AS (SELECT ROW(a,2) LIKE 1 FROM absent) SELECT 1',
            'WITH c AS (SELECT ROW(a,2) LIKE 1 FROM absent) SELECT 1 FROM c',
            'WITH c AS (SELECT ROW(a,2) LIKE 1 FROM absent) SELECT unknown FROM c',
            'WITH c AS (SELECT ROW(a,2) LIKE 1 FROM absent) TABLE c',
            'WITH c AS (SELECT missing(ROW(1,2))) SELECT 1',
            'WITH c AS (SELECT missing(ROW(1,2))) SELECT 1 FROM c',
            'WITH c AS (SELECT missing(ROW(1,2))) SELECT unknown FROM c',
            'WITH c AS (SELECT missing(ROW(1,2))) TABLE c',
            'WITH c AS (SELECT ROW(1,2), missing()) SELECT 1',
            'WITH c AS (SELECT ROW(1,2), missing()) SELECT 1 FROM c',
            'WITH c AS (SELECT ROW(1,2), missing()) SELECT unknown FROM c',
            'WITH c AS (SELECT ROW(1,2), missing()) TABLE c',
            'WITH c AS (SELECT missing(), ROW(1,2)) SELECT 1',
            'WITH c AS (SELECT missing(), ROW(1,2)) SELECT 1 FROM c',
            'WITH c AS (SELECT missing(), ROW(1,2)) SELECT unknown FROM c',
            'WITH c AS (SELECT missing(), ROW(1,2)) TABLE c',
            'WITH c AS (SELECT 1 WHERE ROW(1,2) LIKE 1) SELECT 1',
            'WITH c AS (SELECT 1 WHERE ROW(1,2) LIKE 1) SELECT 1 FROM c',
            'WITH c AS (SELECT 1 WHERE ROW(1,2) LIKE 1) SELECT unknown FROM c',
            'WITH c AS (SELECT 1 WHERE ROW(1,2) LIKE 1) TABLE c',
            'WITH c AS (SELECT 1 AS x ORDER BY ROW(x,2)) SELECT 1',
            'WITH c AS (SELECT 1 AS x ORDER BY ROW(x,2)) SELECT 1 FROM c',
            'WITH c AS (SELECT 1 AS x ORDER BY ROW(x,2)) SELECT unknown FROM c',
            'WITH c AS (SELECT 1 AS x ORDER BY ROW(x,2)) TABLE c',
            'WITH b AS (SELECT 7 AS a), c AS (SELECT ROW(a,2) LIKE 1 FROM b) SELECT 1 FROM c',
            'WITH b AS (SELECT 7 AS a), c AS (SELECT ROW(a,2) LIKE 1 FROM b) SELECT 1',
            'WITH c AS (SELECT ROW(1,2) LIKE 1), d AS (SELECT * FROM c) SELECT 1',
            'WITH c AS (SELECT ROW(1,2) LIKE 1), d AS (SELECT * FROM c) SELECT 1 FROM d',
            'WITH RECURSIVE c AS (SELECT ROW(1,2) LIKE 1 FROM c) SELECT 1',
            'WITH RECURSIVE c AS (SELECT ROW(1,2) LIKE 1 FROM c) SELECT 1 FROM c',
            'WITH c AS (SELECT ROW(1,2) LIKE 1) SELECT 1 FROM t',
            'WITH c AS (SELECT ROW(1,2) LIKE 1) SELECT 1 FROM absent',
            'WITH c AS (SELECT 1 AS a) TABLE c',
            'WITH c AS (SELECT a FROM t) TABLE c',
            'WITH c AS (SELECT a FROM t UNION ALL SELECT 9) TABLE c',
            'WITH c AS (SELECT COUNT(*) AS n FROM t) TABLE c',
            'WITH b AS (SELECT 7 AS a), c AS (TABLE b) TABLE c',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Complete CTE definitions and reached read dependencies
     */
    #[DataProvider('providerReleases')]
    public function testCommonTableBodiesKeepTheirLexicalPreparationOrder(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: $prepared && str_starts_with($sql, 'WITH '))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: $prepared && str_starts_with($sql, 'WITH '))), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
