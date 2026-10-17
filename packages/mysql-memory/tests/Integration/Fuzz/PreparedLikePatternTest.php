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
final class PreparedLikePatternTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE DATABASE d', 'USE d',
            'CREATE TABLE t(a VARCHAR(30), b VARCHAR(30))',
            "INSERT INTO t VALUES ('x','x'),(NULL,'x'),('0','x')",
            "SET @v='x'",
        ];
        foreach (["'x' LIKE CAST('x' AS SIGNED)", "NULL LIKE CAST('x' AS SIGNED)", "'x' NOT LIKE CAST('x' AS SIGNED)", 'NULL LIKE CAST(@v AS SIGNED)', "a LIKE CAST('x' AS SIGNED)", 'a LIKE CAST(b AS SIGNED)', "IF(0, 'x' LIKE CAST('x' AS SIGNED), 1)"] as $expression) {
            $statements[] = 'SELECT ' . $expression . ' AS v FROM t';
            $statements[] = 'SELECT ' . $expression . ' AS v FROM t WHERE 0';
        }
        $statements = [...$statements,
            "KILL 'x' LIKE CAST('x' AS SIGNED)",
            "KILL NULL LIKE CAST('x' AS SIGNED)",
            "SELECT 'x' LIKE CAST('x' AS SIGNED) ESCAPE 'xx'",
            "SELECT 'x' LIKE CAST('x' AS SIGNED) ESCAPE CAST('x' AS SIGNED)",
        ];
        foreach (['5.6.51', '5.7.44', '8.4.7'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Populated inputs, unused expressions and independently checked escape operands
     */
    #[DataProvider('providerReleases')]
    public function testConstantPatternsPreservePreparationAndRowWarnings(string $release, array $statements): void
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
