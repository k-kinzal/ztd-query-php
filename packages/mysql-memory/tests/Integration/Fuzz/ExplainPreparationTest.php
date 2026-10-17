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
final class ExplainPreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $original = <<<'SQL'
EXPLAIN UPDATE IGNORE JSON_TABLE(TRUE, 'ABCDE' 'a' 'PRIMARY' COLUMNS (`1` FOR ORDINALITY, t FOR ORDINALITY, `\` DECIMAL PATH '2024-01-01 01:02:03' 'A1B2C')) AS `あ` SET `2024-01-01` = GET_FORMAT(TIME, N't' '\\'), `2024-01-01 01:02:03` = DEFAULT
SQL;
        $statements = [$original, 'CREATE DATABASE explain_domain', 'USE explain_domain', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(1),(2)'];
        foreach (["'invalid'", "'$'", "'$[*]'"] as $path) {
            foreach (["JSON_TABLE('[1,2]', " . $path . " COLUMNS(a INT PATH '$')) AS j", "t JOIN JSON_TABLE('[1,2]', " . $path . " COLUMNS(a INT PATH '$')) AS j", "missing JOIN JSON_TABLE('[1,2]', " . $path . " COLUMNS(a INT PATH '$')) AS j"] as $table) {
                foreach (['UPDATE ' . $table . ' SET a=1', 'DELETE j FROM ' . $table] as $sql) {
                    array_push($statements, $sql, 'EXPLAIN ' . $sql);
                }
            }
        }
        array_push($statements, 'EXPLAIN UPDATE (SELECT 1 AS a) AS j SET a=1', "EXPLAIN UPDATE JSON_TABLE('[1,2]', '$[*]' COLUMNS(a INT PATH 'invalid')) AS j SET a=1", "EXPLAIN UPDATE JSON_TABLE('[1,2]', '$[*]' COLUMNS(a INT PATH '$')) AS j SET a=1");
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements
     */
    #[DataProvider('providerReleases')]
    public function testExplainPreparesJsonPathsBeforeTargetConditions(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

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
