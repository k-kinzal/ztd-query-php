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
final class LoadExpressionPriorityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', 'CREATE TABLE t2(a INT,b INT)'];
        foreach (['DATA', 'XML'] as $format) {
            foreach (['', 'LOCAL '] as $local) {
                foreach (['(1,2)', '(1,2)=(3,4,5)', '1=ALL(SELECT * FROM t2)', '1=ALL(SELECT * FROM missing)', '(1,2) IN (3)', 'ABS()', '@@no_such_variable'] as $expression) {
                    $statements[] = "LOAD $format {$local}INFILE 'f' INTO TABLE t SET a=$expression";
                }
            }
        }
        $statements = [...$statements, 'SELECT (1,2)=(3,4,5),', 'SELECT (1,2)=(3,4,5), BINARY 1', 'SELECT (1,2)=(3,4,5), ABS()', 'CREATE PROCEDURE broken() BEGIN SELECT (1,2)=(3,4,5); SELECT ; END', 'CREATE PROCEDURE p() SELECT (1,2)=(3,4,5)', 'CREATE PROCEDURE q() BEGIN IF FALSE THEN SELECT (1,2)=(3,4,5); END IF; END', 'CALL q()', 'CREATE PROCEDURE r() SELECT (1,2)=3', 'CALL r()'];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Load expressions and stored definitions in native preparation order
     */
    #[DataProvider('providerReleases')]
    public function testLoadEntryConditionsPrecedePreparationButFollowGrammarConditions(string $release, array $statements): void
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
