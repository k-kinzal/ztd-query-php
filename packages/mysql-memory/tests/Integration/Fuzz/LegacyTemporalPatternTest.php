<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class LegacyTemporalPatternTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(s VARCHAR(20) CHARACTER SET utf8, d DATE, dt DATETIME, tm TIME)', "INSERT INTO t VALUES('x','2020-01-02','2020-01-02 03:04:05','03:04:05')"];
        foreach (['latin1', 'utf8', 'utf8mb4'] as $charset) {
            $statements[] = 'SET NAMES ' . $charset;
            foreach (["'x'", "'20200102'", "'2020-1-2'", "'2020-01-02trail'", "'2020-01-02 03:04:05'", "'20200102030405'", "'0000-00-00'", "'2020-13-02'", "_binary'x'", "_utf8'x' COLLATE utf8_general_ci", "_latin1'x' COLLATE latin1_bin", 's', 'DATABASE()', 'NULL', '1'] as $text) {
                foreach (["DATE('2020-01-02')", "TIMESTAMP('2020-01-02 03:04:05')", "DATE('x')", "TIME('x')", "CAST('x' AS DATETIME)", 'd', 'dt', 'tm'] as $temporal) {
                    $statements[] = 'SELECT ' . $text . ' LIKE ' . $temporal . ' AS v FROM t';
                    $statements[] = 'SELECT ' . $temporal . ' LIKE ' . $text . ' AS v FROM t';
                }
            }
        }
        foreach (['5.6.51', '5.7.44', '8.4.7'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Temporal expressions and columns, constant and row-dependent strings, both operand orders
     */
    #[DataProvider('providerStatements')]
    public function testPreparationRetainsOriginalStringsAndNativeConditions(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
