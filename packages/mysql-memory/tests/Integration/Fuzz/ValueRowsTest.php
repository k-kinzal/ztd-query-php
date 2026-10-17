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
final class ValueRowsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = ['CREATE DATABASE placement', 'USE placement', 'CREATE TABLE t(a INT DEFAULT 7,b INT DEFAULT 8)'];
        foreach (['VALUES ROW()', 'VALUES ROW(DEFAULT)', 'VALUES ROW(1), ROW(1,2)', 'VALUES ROW(), ROW(1,2)', 'VALUES ROW(DEFAULT), ROW()', 'VALUES ROW(1,2), ROW()', 'VALUES ROW(no_such_column), ROW()', 'VALUES ROW(), ROW(no_such_column)'] as $query) {
            array_push(
                $statements,
                $query,
                'INSERT INTO t ' . $query,
                'INSERT INTO t ' . $query . ' LIMIT 1',
                'INSERT INTO t (' . $query . ')',
                'SELECT * FROM (' . $query . ') AS d',
                'CREATE PROCEDURE p() ' . $query,
                'CALL p()',
                'DROP PROCEDURE IF EXISTS p',
                'CREATE PROCEDURE p() BEGIN IF FALSE THEN ' . $query . '; END IF; END',
                'CALL p()',
                'DROP PROCEDURE IF EXISTS p',
                'SELECT * FROM t',
                'DELETE FROM t',
            );
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements VALUES histories in query, INSERT and stored-program positions
     */
    #[DataProvider('providerStatements')]
    public function testValuesKeepTheirReadWriteAndDeferredProgramConditions(string $release, array $statements): void
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
