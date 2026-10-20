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
final class NestedSubqueryShapeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $queries = [];
        foreach (['ROW(1,ROW(2,3,4))', 'ROW(NULL,1,ROW(2,3))', 'ROW(missing,ROW(2,3))', 'ROW(1,ROW(2,(SELECT x FROM absent)))', 'ROW((SELECT * FROM u),1)'] as $row) {
            foreach (['IN', '= ANY', '<> ALL'] as $operator) {
                $queries[] = 'SELECT ' . $row . ' ' . $operator . ' (SELECT * FROM t)';
            }
        }
        $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'USE d'];
        foreach ([[], ['CREATE TABLE t(a INT)'], ['ALTER TABLE t ADD b INT'], ['ALTER TABLE t ADD c INT'], ['INSERT INTO t VALUES(1,2,3)'], ['CREATE TABLE u(a INT)'], ['ALTER TABLE u ADD b INT']] as $setup) {
            array_push($statements, ...$setup);
            foreach ($queries as $sql) {
                array_push($statements, $sql, "PREPARE p FROM '" . str_replace("'", "''", $sql) . "'");
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Unchanged nested operands across missing, empty, populated and changed-width tables
     */
    #[DataProvider('providerInputs')]
    public function testFlatQueryOutputRetainsDependencyAndWidthConditions(string $release, array $statements): void
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
