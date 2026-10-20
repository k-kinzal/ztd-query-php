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
final class FunctionalColumnRoleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $queries = [];
        foreach (['a', '((a))', 'missing', 't.a'] as $operand) {
            array_push($queries, 'CREATE INDEX i ON t ((' . $operand . '))', 'ALTER TABLE t ADD INDEX i ((' . $operand . '))');
        }
        $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'USE d'];
        foreach ([[], ['CREATE TABLE t(a INT)'], ['INSERT INTO t VALUES(7)']] as $setup) {
            array_push($statements, ...$setup);
            foreach ($queries as $sql) {
                array_push($statements, $sql, "PREPARE p FROM '" . str_replace("'", "''", $sql) . "'", 'EXECUTE p', 'DEALLOCATE PREPARE p');
            }
        }
        foreach (['a', '((a))', 'missing', 'u.a'] as $operand) {
            array_push($statements, 'CREATE TABLE u(a INT, INDEX i ((' . $operand . ')))', 'CREATE TABLE IF NOT EXISTS t(a INT, INDEX i ((' . $operand . ')))');
        }
        array_push($statements, 'CREATE PROCEDURE run_key(flag INT) BEGIN IF flag THEN CREATE INDEX j ON t ((a)); END IF; END', 'CALL run_key(0)', 'CALL run_key(1)', 'CALL run_key(0)', 'SELECT * FROM t');
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Bare column keys across missing, conditional, prepared and stored definitions
     */
    #[DataProvider('providerInputs')]
    public function testBareColumnRolePreservesDefinitionAndInvocationBoundaries(string $release, array $statements): void
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
