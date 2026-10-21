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
final class SampledWriteTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, list<string>}>
     */
    public static function providerHistories(): iterable
    {
        $writes = [
            'UPDATE t TABLESAMPLE BERNOULLI(10) SET zz=1',
            'UPDATE t SET zz=(SELECT 1 FROM u TABLESAMPLE SYSTEM(5))',
            'WITH q AS (SELECT * FROM u TABLESAMPLE SYSTEM(5)) UPDATE t SET a=1',
            'DELETE t FROM t TABLESAMPLE SYSTEM(5)',
            'DELETE FROM t WHERE EXISTS(SELECT 1 FROM u TABLESAMPLE SYSTEM(5))',
            'WITH q AS (SELECT * FROM u TABLESAMPLE SYSTEM(5)) DELETE FROM t',
            'UPDATE t FORCE INDEX(nope) TABLESAMPLE SYSTEM(500) SET zz=1',
            'UPDATE t PARTITION(nope) TABLESAMPLE SYSTEM(5) SET a=1',
            'WITH q AS (SELECT * FROM u TABLESAMPLE SYSTEM(5)) UPDATE t SET a=(SELECT COUNT(*) FROM q)',
            'WITH q AS (SELECT * FROM u TABLESAMPLE SYSTEM(5)) DELETE FROM t WHERE EXISTS(SELECT 1 FROM q)',
        ];
        $statements = ['CREATE DATABASE d', 'USE d', ...$writes, 'CREATE TABLE t(a INT)', ...$writes, 'CREATE TABLE u(a INT)', 'INSERT INTO t VALUES(7)', 'INSERT INTO u VALUES(7)', ...$writes];
        foreach ($writes as $index => $sql) {
            array_push($statements, 'CREATE PROCEDURE p' . $index . '(IN active BOOLEAN) BEGIN IF active THEN ' . $sql . '; END IF; END', 'CALL p' . $index . '(FALSE)', 'CALL p' . $index . '(TRUE)');
        }
        array_push($statements, 'SELECT * FROM t', 'SELECT * FROM u');
        foreach (['8.4.7', '9.0.1', '9.1.0'] as $release) {
            foreach ([false, true] as $prepared) {
                yield $release . ':' . (int) $prepared => [$release, $prepared, $statements];
            }
        }
    }

    /**
     * @param list<string> $statements Reached and unused sampling clauses across table and invocation states
     */
    #[DataProvider('providerHistories')]
    public function testSampledWritesRetainDependencyPriorityAndInactiveDefinitions(string $release, bool $prepared, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $differences = array_filter(array_map(static function (string $sql) use ($native, $memory, $prepared): ?array {
                    $binary = $prepared && preg_match('/\A(?:UPDATE|DELETE|WITH|CALL)\b/', $sql) === 1;
                    $expected = (new Native())->observe($native, $sql, prepared: $binary);
                    $actual = (new Native())->observe($memory, $sql, prepared: $binary);
                    return serialize($expected) === serialize($actual) ? null : [$sql, $expected, $actual];
                }, $statements));
                self::assertSame([], array_values($differences), $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
