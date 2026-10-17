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
use RuntimeException;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DeleteTargetNamespaceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     * @throws RuntimeException When the retained generated input is missing
     */
    public static function providerReleases(): iterable
    {
        $retained = file_get_contents(__DIR__ . '/fixtures/delete-target-namespace.sql');
        if ($retained === false) {
            throw new RuntimeException('The retained deletion input is missing.');
        }
        $statements = [
            'DELETE x FROM d.t AS y',
            'CREATE PROCEDURE d.p() DELETE x FROM d.t AS y',
            'CREATE PROCEDURE p() DELETE x FROM d.t AS y',
            'DELETE d.x FROM d.t AS y',
            'DELETE d.x FROM d.t AS y, d.t AS y',
            'DELETE d.x FROM d.t AS y WHERE ABS()',
            'CREATE DATABASE d',
            'USE d',
            'CREATE TABLE t(a INT)',
            "DELETE missing FROM JSON_TABLE('[]', '$[*]' COLUMNS(a INT PATH '$' ERROR ON ERROR ERROR ON EMPTY)) AS j",
            "DELETE missing FROM JSON_TABLE('[]', '$[*]' COLUMNS(a INT PATH '$' ERROR ON ERROR ERROR ON EMPTY)) AS j WHERE ABS()",
            "DELETE missing FROM JSON_TABLE('[]', '$[*]' COLUMNS(a INT PATH '$' ERROR ON ERROR ERROR ON EMPTY)) AS j BOGUS",
            'DELETE x FROM t AS y',
            'DELETE d.x FROM t AS y',
            'DELETE d.y FROM t AS y',
            'DELETE d.t FROM t AS y',
            'DELETE t FROM t AS y',
            'DELETE d.x FROM t AS y,t AS y',
            'DELETE x FROM t AS y WHERE ABS()',
            'DELETE x FROM t AS y WHERE unknown_col',
            'DELETE x FROM (SELECT missing FROM nowhere) AS y',
            'CREATE PROCEDURE p() DELETE x FROM t AS y',
            'CREATE PROCEDURE p() BEGIN IF 0 THEN DELETE x FROM t AS y; END IF; END',
            'DELETE x FROM (SELECT 1) AS y WHERE @@unknown_variable',
            'DELETE x FROM (SELECT 1) AS y, (SELECT 2) AS y',
            'DELETE x FROM (SELECT HIGH_PRIORITY 1) AS y',
            'CREATE PROCEDURE d.q() BEGIN IF 0 THEN DELETE x FROM t AS y; END IF; END',
            'CREATE PROCEDURE other.p() DELETE x FROM t AS y',
            'DELETE T FROM t',
            'DELETE d.t FROM d.t',
            'DELETE FROM x USING t AS y',
            'DELETE t, x FROM other.t',
            'DELETE T, x FROM t',
            'DELETE t, x FROM t',
            'DELETE y, x FROM (SELECT 1) AS y',
            'CREATE PROCEDURE d.p() BEGIN DELETE x FROM t AS y; BOGUS; END',
            'CREATE PROCEDURE d.p() BEGIN DELETE d.x FROM d.t AS y; BOGUS; END',
            "SHOW SESSION STATUS LIKE 'Com_delete_multi'",
            "SHOW SESSION STATUS LIKE 'Com_create_procedure'",
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            yield $release => [$release, $release === '8.0.44' ? [...$statements, $retained] : $statements];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testDisconnectedTargetsAreRejectedEvenInUnreachableStoredInstructions(string $release, array $statements): void
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
