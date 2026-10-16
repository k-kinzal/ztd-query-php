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
final class ArrayIndexTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = ['CREATE DATABASE placement', 'USE placement'];
        $values = ['NULL', "'null'", "'[]'", "'[1,2,1]'", "'[null]'", "'1'", "'1.5'", "'true'", "'{}'", "'[[1]]'", "'[true,false]'", "'[\"1\",\"2\"]'", "'[\"bad\"]'", "'[\"abcdef\"]'", "'[-1,18446744073709551615]'", "'[1.234,2.345]'", "'[\"2024-01-01\"]'", "'[\"12:34:56\"]'", "'[\"2024-01-01 12:34:56\"]'"];
        foreach (['SIGNED', 'UNSIGNED', 'CHAR(5)', 'BINARY(5)', 'DECIMAL(6,2)', 'DATE', 'TIME', 'DATETIME'] as $target) {
            $statements[] = 'CREATE TABLE t(id INT PRIMARY KEY,j JSON,UNIQUE KEY k((CAST(j AS ' . $target . ' ARRAY))))';
            array_push($statements, 'SHOW CREATE TABLE t', 'SHOW INDEX FROM t', 'SHOW COLUMNS FROM t');
            foreach ($values as $position => $value) {
                array_push($statements, 'INSERT INTO t VALUES(' . ($position + 10) . ',' . $value . ')', 'SELECT * FROM t', 'DELETE FROM t');
            }
            array_push($statements, "INSERT INTO t VALUES(1,'[3,2,1]')", "INSERT INTO t VALUES(2,'[3,2,1]')", 'DELETE FROM t', 'DROP TABLE t');
        }
        array_push(
            $statements,
            'CREATE TABLE t(id INT PRIMARY KEY,j JSON,UNIQUE KEY k((CAST(j AS DECIMAL(6,2) ARRAY))))',
            "INSERT INTO t VALUES(1,'[1.234,2.345]'),(2,'[3.456]')",
            'UPDATE t SET id=id',
            'UPDATE t SET id=id+10',
            'SELECT * FROM t ORDER BY id',
            'DELETE FROM t WHERE id=11',
            'DELETE FROM t',
            'DROP TABLE t',
            'CREATE TABLE t(j JSON)',
            'CREATE INDEX k ON t((CAST(j AS SIGNED ARRAY)) ASC)',
            'CREATE INDEX k ON t((CAST(j AS SIGNED ARRAY)),(CAST(j AS SIGNED ARRAY)))',
            'CREATE INDEX k ON t((CAST(j AS CHAR(5) CHARACTER SET latin1 ARRAY)))',
            'CREATE TABLE IF NOT EXISTS t(j JSON,KEY k((CAST(j AS SIGNED ARRAY)) DESC))',
            'DROP TABLE t',
            'CREATE INDEX k ON t((CAST(j AS SIGNED ARRAY)) ASC)',
        );
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Array index conversion, conflict, write and declaration histories
     */
    #[DataProvider('providerStatements')]
    public function testArrayIndexesPreserveWritesConversionsAndMetadata(string $release, array $statements): void
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
