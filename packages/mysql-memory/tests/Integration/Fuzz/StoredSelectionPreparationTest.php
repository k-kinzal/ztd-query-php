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
final class StoredSelectionPreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        $statements[] = 'CREATE TABLE t(a INT)';
        $statements[] = 'INSERT INTO t VALUES (7)';
        $bodies = ['SELECT COUNT(*),(1,2)', 'SELECT COUNT(missing),(1,2)', 'SELECT missing+SUM(other),(1,2)', 'SELECT SUM(COUNT(*)),(1,2)', 'SELECT q.*,(1,2) FROM t', 'SELECT (1,2),q.* FROM t', 'SELECT *,(1,2)', 'SELECT (1,2),SUM(missing)', 'SELECT SUM(a),(1,2) FROM t', 'SELECT 1 FROM t WHERE (missing,2)'];
        foreach ($bodies as $body) {
            $statements[] = 'SET @x=1,@y=2';
            $statements[] = 'DROP PROCEDURE IF EXISTS p';
            $statements[] = 'CREATE PROCEDURE p(INOUT p INT,INOUT q INT) ' . $body;
            $statements[] = 'CALL p(@x,@y)';
            $statements[] = 'SELECT @x,@y';
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Stored definitions, reached instructions and skipped instructions
     */
    #[DataProvider('providerReleases')]
    public function testStoredSelectionsPrepareStarsNamesAndAggregatesBeforeTheirRowCondition(string $release, array $statements): void
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
