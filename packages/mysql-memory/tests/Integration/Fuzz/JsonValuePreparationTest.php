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
final class JsonValuePreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE DATABASE d',
            'USE d',
            'CREATE TABLE t (i INT, s VARCHAR(10), j JSON)',
            'INSERT INTO t VALUES (NULL,NULL,NULL)',
            'SET @nil=NULL',
            'SELECT JSON_VALUE(@nil, \'\') FROM t',
            'SELECT JSON_VALUE(@nil, \'$\') FROM t',
            'SELECT JSON_VALUE(@unset, \'\') FROM t',
            'SELECT JSON_VALUE(@unset, \'$\') FROM t',
            'SELECT JSON_VALUE(?, \'\') FROM t',
            'SELECT JSON_VALUE(?, \'$\') FROM t',
            'SELECT JSON_VALUE((?), \'\') FROM t',
            'SELECT JSON_VALUE((?), \'$\') FROM t',
            'SELECT JSON_VALUE(COALESCE(NULL,NULL), \'\') FROM t',
            'SELECT JSON_VALUE(COALESCE(NULL,NULL), \'$\') FROM t',
            'SELECT JSON_VALUE(IF(TRUE,NULL,NULL), \'\') FROM t',
            'SELECT JSON_VALUE(IF(TRUE,NULL,NULL), \'$\') FROM t',
            'SELECT JSON_VALUE(NULLIF(1,1), \'\') FROM t',
            'SELECT JSON_VALUE(NULLIF(1,1), \'$\') FROM t',
            'SELECT JSON_VALUE(i, \'\') FROM t',
            'SELECT JSON_VALUE(i, \'$\') FROM t',
            'SELECT JSON_VALUE(s, \'\') FROM t',
            'SELECT JSON_VALUE(s, \'$\') FROM t',
            'SELECT JSON_VALUE(j, \'\') FROM t',
            'SELECT JSON_VALUE(j, \'$\') FROM t',
            'SELECT JSON_VALUE(CAST(? AS CHAR), \'$\')',
            'SELECT JSON_VALUE(CAST(? AS JSON), \'$\')',
            'SELECT COALESCE(JSON_VALUE(?, \'\'), FALSE, GET_FORMAT(DATE, ?))',
            "SELECT JSON_VALUE(NULL, ''), JSON_VALUE(0, '$')",
            "SELECT JSON_VALUE(0, '$'), JSON_VALUE(NULL, '')",
            "SELECT missing, JSON_VALUE(0, '$')",
            "SELECT JSON_VALUE(0, '$'), missing",
            "SELECT JSON_VALUE(CAST(missing AS SIGNED), '')",
            "SELECT JSON_VALUE(CAST(missing_function() AS SIGNED), '')",
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testDocumentTypesAreResolvedBeforeConstantPaths(string $release, array $statements): void
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
