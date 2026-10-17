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
final class SubqueryReadConstraintTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $queries = [
            'SELECT (SELECT *)',
            'SELECT (SELECT * FROM DUAL)',
            'SELECT (SELECT 1 UNION SELECT 1,2)',
            'SELECT (SELECT missing UNION SELECT 1,2)',
            'SELECT (SELECT missing FROM absent UNION SELECT 1,2)',
            'SELECT (VALUES ROW())',
            'SELECT (VALUES ROW(1),ROW(2,3))',
            'SELECT (VALUES ROW(DEFAULT))',
            'SELECT (WITH c AS (SELECT *) SELECT 1)',
            'SELECT (WITH c AS (SELECT *) SELECT * FROM c)',
            'SELECT (SELECT 1 UNION SELECT missing,2 FROM t)',
            'SELECT (SELECT * FROM t)',
        ];
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(1),(2)'];
        foreach ($queries as $index => $query) {
            $prepared = "PREPARE s FROM '" . str_replace("'", "''", $query) . "'";
            array_push($statements, $query, 'CREATE PROCEDURE p' . $index . '() BEGIN IF @run THEN ' . $query . '; END IF; END', 'SET @run=0', 'CALL p' . $index . '()', 'SET @run=1', 'CALL p' . $index . '()', $prepared, 'DEALLOCATE PREPARE s', 'CREATE VIEW vw' . $index . ' AS ' . $query);
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Subquery read constraints through direct, stored, prepared and view contexts
     */
    #[DataProvider('providerInputs')]
    public function testReadConstraintsPreserveDependencyAndPreparationOrder(string $release, array $statements): void
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
