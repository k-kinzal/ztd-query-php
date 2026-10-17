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
final class DeferredKillTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        $bodies = [
            'row' => 'KILL ROW(1,2)',
            'pattern' => 'KILL 1 LIKE ROW(1,2)',
            'truth' => 'KILL (1 LIKE ROW(1,2)) IS TRUE',
            'missing_column' => 'KILL ROW(missing,2)',
            'missing_function' => 'KILL ROW(missing(),2)',
            'table' => 'KILL (SELECT a,b FROM absent)',
            'subquery' => 'KILL (SELECT 1,2)',
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
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            $cases = [...$statements,
                'KILL ROW(1,2)', 'KILL 1 LIKE ROW(1,2)',
                'KILL ROW(@@missing,2)', 'CREATE PROCEDURE bad() KILL ROW(@@missing,2)',
                'KILL 1 IN (SELECT 1 LIMIT 1)',
                'SELECT missing IN (SELECT 1 LIMIT 1) FROM absent',
                'CREATE PROCEDURE bad_limit() KILL 1 IN (SELECT 1 LIMIT 1)',
                'CREATE PROCEDURE skipped_limit() BEGIN IF 0 THEN SELECT 1 IN (SELECT 1 LIMIT 1); END IF; END',
            ];
            if (!str_starts_with($release, '5.')) {
                $cases = [...$cases, 'KILL 1 MEMBER OF (ROW(1,2))',
                    'KILL 1 = (1 MEMBER OF (ROW(1,2))) IS TRUE',
                    'KILL ROW(1,2) = (1 MEMBER OF (ROW(1,2)))',
                    'CREATE PROCEDURE member_use() BEGIN IF @run THEN KILL 1 MEMBER OF (ROW(1,2)); END IF; END',
                    'SET @run=0', 'CALL member_use()', 'SET @run=1', 'CALL member_use()',
                ];
            }
            yield $release => [$release, $cases];
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
