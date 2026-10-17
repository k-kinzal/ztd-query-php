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
final class DeferredRowRolesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        $bodies = [
            'projection' => 'SELECT (1,2)',
            'arithmetic' => 'SELECT 1 + (1,2)',
            'call_arg' => 'SELECT ABS((1,2))',
            'predicate' => 'IF (1,2) THEN SELECT 1; END IF',
            'loop_test' => 'WHILE (1,2) DO SELECT 1; END WHILE',
            'initial_value' => 'DECLARE x INT DEFAULT (1,2); SELECT x',
            'assignment' => 'SET @x=(1,2)',
            'query_filter' => 'SELECT 1 FROM DUAL WHERE (1,2)',
            'nested_query' => 'SELECT (SELECT 1,2)',
            'membership' => 'SELECT (1,2) IN (3)',
        ];
        foreach ($bodies as $name => $body) {
            $statements[] = "CREATE PROCEDURE p_$name() BEGIN IF @run THEN BEGIN $body; END; END IF; END";
            $statements[] = 'SET @run=0';
            $statements[] = "CALL p_$name()";
            $statements[] = 'SET @run=1';
            $statements[] = "CALL p_$name()";
            $statements[] = 'SET @run=0';
            $statements[] = "CALL p_$name()";
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Stored definitions, reached instructions and skipped instructions
     */
    #[DataProvider('providerReleases')]
    public function testStoredRowUsesFailOnlyWhenControlReachesThem(string $release, array $statements): void
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
