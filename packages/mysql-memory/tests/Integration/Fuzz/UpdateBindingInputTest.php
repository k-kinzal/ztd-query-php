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
final class UpdateBindingInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $writes = [
            'UPDATE t,u SET t.a=missing',
            'UPDATE t,u SET missing=2 WHERE absent',
            'UPDATE t,u SET t.a=missing WHERE absent',
            'UPDATE t JOIN u ON absent SET missing=2',
            'UPDATE t JOIN u ON absent SET t.a=missing',
            'UPDATE t,u SET t.a=no_such_function()',
            'UPDATE t,u SET t.a=(SELECT missing)',
            'UPDATE t,u SET t.a=(SELECT absent FROM absent_table)',
            'UPDATE t,u SET t.a=NOW(9)',
            'UPDATE t,u SET t.a=1 WHERE NOW(9)',
            'UPDATE t JOIN u ON NOW(9) SET t.a=1',
            'UPDATE t,u SET t.a=AVG(u.a)',
            'UPDATE t,u SET t.a=AVG(missing)',
            'UPDATE t SET a=missing ORDER BY absent',
            'UPDATE t SET missing=1 ORDER BY absent',
            'UPDATE t JOIN (SELECT missing) q ON TRUE SET t.a=1',
            'UPDATE t JOIN (SELECT AVG(missing)) q ON TRUE SET t.a=1',
            'UPDATE t,u SET t.a=(SELECT AVG(a) FROM v)',
            'UPDATE t,u SET t.a=ROW_NUMBER() OVER ()',
        ];
        $prepared = [];
        foreach ($writes as $sql) {
            array_push($prepared, "PREPARE st FROM '" . $sql . "'", 'EXECUTE st');
        }
        $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT,b INT)', 'CREATE TABLE u(a INT,b INT)', 'CREATE TABLE v(a INT)', ...$writes, ...$prepared, 'INSERT INTO t VALUES(1,2)', 'INSERT INTO u VALUES(3,4)', 'INSERT INTO v VALUES(5),(7)', ...$writes, ...$prepared, 'SELECT * FROM t', 'SELECT * FROM u'];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Joined and single-table writes before and after preparation and row setup
     */
    #[DataProvider('providerStatements')]
    public function testUpdateBindingPreservesPreparationAndExecutionPhases(string $release, array $statements): void
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
