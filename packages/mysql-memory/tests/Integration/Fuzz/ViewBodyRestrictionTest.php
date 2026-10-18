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
final class ViewBodyRestrictionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $bodies = ['SELECT @x', 'SELECT @@missing', 'SELECT ?', 'VALUES ROW(?)', 'SELECT ROW(@x,2)', 'SELECT missing,@x FROM absent', 'SELECT (SELECT @x)', 'SELECT 1 UNION SELECT @x', 'WITH c AS (SELECT @x) SELECT 1', 'SELECT 1 INTO @x', 'SELECT @x INTO @y', "SELECT 1 INTO OUTFILE '/tmp/unreachable-view-output'", 'SELECT SQL_CACHE 1 INTO @x UNION SELECT SQL_CACHE 2', 'SELECT 1 INTO @x UNION SELECT @x', 'SELECT ``.*,@x', 'SELECT missingdb.t.*,@x FROM missingdb.t'];
        $statements = [];
        foreach ([[], ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', 'CREATE VIEW v AS SELECT 1 AS a']] as $setup) {
            array_push($statements, ...$setup);
            foreach (['CREATE VIEW', 'ALTER VIEW', 'CREATE OR REPLACE VIEW'] as $head) {
                foreach (['v', 'missingdb.v'] as $name) {
                    foreach ($bodies as $body) {
                        $statements[] = $head . ' ' . $name . ' AS ' . $body;
                    }
                }
            }
        }
        foreach (['absent', str_repeat('u', 40)] as $definer) {
            foreach (['SELECT @x', 'SELECT ROW(@x,2)', 'SELECT 1 INTO @x'] as $body) {
                $statements[] = 'CREATE DEFINER = ' . $definer . ' VIEW v AS ' . $body;
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Direct and prepared view inputs across catalog states
     */
    #[DataProvider('providerReleases')]
    public function testIntrinsicViewRefusalsRetainTheirInputPriority(string $release, array $statements): void
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
