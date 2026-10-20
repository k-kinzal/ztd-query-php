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
final class TriggerSchemaDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $queries = ['CREATE TRIGGER a.tr BEFORE INSERT ON b.t FOR EACH ROW SET @x=1', 'CREATE TRIGGER a.tr BEFORE INSERT ON b.t FOR EACH ROW RETURN 1', 'CREATE TRIGGER a.tr BEFORE INSERT ON b.t FOR EACH ROW SIGNAL missing_condition', "CREATE DEFINER='missing'@'localhost' TRIGGER a.tr BEFORE INSERT ON b.t FOR EACH ROW SET @x=1"];
        $statements = ['SET NAMES utf8mb4'];
        foreach ([[], ['CREATE DATABASE a', 'CREATE DATABASE b'], ['CREATE TABLE b.t(a INT)'], ['USE a'], ['DROP TABLE b.t', 'CREATE VIEW b.t AS SELECT 1 AS a']] as $setup) {
            array_push($statements, ...$setup);
            foreach ($queries as $sql) {
                array_push($statements, $sql, "SHOW SESSION STATUS LIKE 'Com_create_trigger'", "PREPARE p FROM '" . str_replace("'", "''", $sql) . "'", "SHOW SESSION STATUS LIKE 'Com_create_trigger'");
            }
        }
        array_push($statements, 'DROP VIEW b.t', 'CREATE TABLE b.t(a INT)', 'START TRANSACTION', 'INSERT INTO b.t VALUES(7)', $queries[0], 'ROLLBACK', 'SELECT * FROM b.t');
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Unchanged requests across catalog, preparation and transaction histories
     */
    #[DataProvider('providerInputs')]
    public function testSchemaRefusalPreservesTheNativeExecutionBoundary(string $release, array $statements): void
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
