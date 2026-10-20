<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use JsonException;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class UpdateAliasInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     * @throws JsonException When the retained fuzz fixture is invalid
     */
    public static function providerStatements(): iterable
    {
        /** @var list<array{sql: string}> $fixture */
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/update-fixed-names.json'), true, 512, JSON_THROW_ON_ERROR);
        $statements = [$fixture[0]['sql'],
            'UPDATE t,t SET a=1',
            'UPDATE t AS a,u AS a SET a.x=1',
            'UPDATE t AS a,.a SET x=1',
            'UPDATE d.t AS a,e.u AS a SET x=1',
            'CREATE DATABASE d',
            'USE d',
            'UPDATE t,t SET a=1',
            'UPDATE t AS a,u AS a SET a.x=1',
            'UPDATE t AS a,.a SET x=1',
            'CREATE PROCEDURE p() UPDATE t,t SET a=1',
            'CREATE PROCEDURE q() BEGIN IF @run THEN UPDATE t AS a,u AS a SET a.x=1; END IF; END',
            'CALL p()', 'SET @run=0', 'CALL q()', 'SET @run=1', 'CALL q()',
            "PREPARE s FROM 'UPDATE t,t SET a=1'", 'EXECUTE s',
            'CREATE TABLE t(x INT)',
            'CREATE TABLE u(x INT)',
            'INSERT INTO t VALUES (0),(2)',
            'UPDATE t,t SET x=1',
            'UPDATE t AS a,u AS a SET a.x=1',
            'UPDATE d.t AS a,information_schema.TABLES AS a SET a.x=1',
            'SELECT x FROM t ORDER BY x',
            'CREATE DATABASE e',
            'CREATE TABLE e.u(x INT)',
            'UPDATE d.t AS a,e.u AS a SET a.x=1',
            'DROP DATABASE e',
            'DROP DATABASE d',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Repeated names before and after selecting a database and creating their physical inputs
     */
    #[DataProvider('providerStatements')]
    public function testUpdateNamesKeepDatabaseSelectionAndAliasChecksInOrder(string $release, array $statements): void
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
