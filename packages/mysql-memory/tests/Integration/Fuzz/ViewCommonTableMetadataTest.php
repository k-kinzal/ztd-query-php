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
final class ViewCommonTableMetadataTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES (3),(7)'];
        foreach (['WITH c AS (SELECT @x) SELECT a FROM t', 'WITH c AS (SELECT a FROM t) SELECT a FROM c', 'WITH c AS (SELECT DISTINCT a FROM t) SELECT a FROM c', 'WITH c AS (SELECT 1 AS a) SELECT a FROM c', 'WITH c AS (SELECT a+1 AS a FROM t LIMIT 2) SELECT a FROM c', 'WITH RECURSIVE c AS (SELECT 1 AS a UNION ALL SELECT a+1 FROM c WHERE a<3) SELECT a FROM c', 'WITH c AS (SELECT a FROM t) SELECT a FROM c ORDER BY a'] as $body) {
            $statements[] = $body;
            foreach (['', 'ALGORITHM=TEMPTABLE '] as $algorithm) {
                $statements[] = 'CREATE OR REPLACE ' . $algorithm . 'VIEW v AS ' . $body;
                $statements[] = 'SELECT * FROM v';
            }
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Common tables read directly and through views
     */
    #[DataProvider('providerReleases')]
    public function testCommonTablesPreserveColumnIdentityThroughBothViewAlgorithms(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, substr_count($sql, '?'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, substr_count($sql, '?'))), $statements);

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
