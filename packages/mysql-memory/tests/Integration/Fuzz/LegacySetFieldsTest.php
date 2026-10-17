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
final class LegacySetFieldsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a TINYINT,b SMALLINT,c MEDIUMINT,i INT,u BIGINT UNSIGNED,f FLOAT,h FLOAT(8,2),d DOUBLE,dc DECIMAL(8,2),g DATE,s VARCHAR(8))', "INSERT INTO t VALUES(1,2,3,4,18446744073709551615,1.5,1.25,2.5,3.25,'2020-01-02','abc')"];
        $operands = ['NULL', '1', '-1', '2147483648', '18446744073709551615', '1.25', '1e0', "'abc'", 'a', 'b', 'c', 'i', 'u', 'f', 'h', 'd', 'dc', 'g', 's', 'ABS(2147483648)', 'CAST(2147483648 AS SIGNED)'];
        foreach ($operands as $left) {
            foreach ($operands as $right) {
                foreach (['UNION', 'UNION ALL'] as $operator) {
                    $query = 'SELECT ' . $left . ' AS value FROM t ' . $operator . ' SELECT ' . $right . ' FROM t';
                    $statements[] = $query;
                    $statements[] = 'SELECT value FROM (' . $query . ') q';
                }
            }
        }
        foreach (['5.6.51', '5.7.44'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Numeric and textual operands in both orders, with direct and stored consumers
     */
    #[DataProvider('providerReleases')]
    public function testLegacySetFieldsAndStoredValuesAgreeWithNativeMySql(string $release, array $statements): void
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

                array_map(static function (string $reference, string $observed, string $sql) use ($release): void {
                    self::assertSame($reference, $observed, $release . ': ' . $sql);
                }, $expected, $actual, $statements);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
