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
final class InsertIntoSourceInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     * @throws JsonException When the retained fuzz fixture is invalid
     */
    public static function providerStatements(): iterable
    {
        /** @var list<array{sql: string}> $fixture */
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/insert-into-source.json'), true, 512, JSON_THROW_ON_ERROR);
        $writes = [
            $fixture[0]['sql'],
            "REPLACE name.t SELECT 1 INTO DUMPFILE 'text' WHERE ROW(1,2)",
            'INSERT name.t SELECT 1 INTO @x WHERE ROW(1,2)',
            'INSERT name.t SELECT 1 INTO x WHERE ROW(1,2)',
        ];
        $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'USE d', ...$writes,
            'CREATE DATABASE name', ...$writes,
            'CREATE TABLE name.t(a INT)', 'CREATE TABLE name.CLONE(a INT)', ...$writes,
            'INSERT INTO name.t VALUES(1)', ...$writes,
            'SELECT 1 INTO @x WHERE ROW(1,2)', 'SELECT 1 INTO x WHERE ROW(1,2)',
            'SELECT 1 INTO x FROM DUAL WHERE (', 'SELECT 1 FROM DUAL INTO x WHERE (',
            'SELECT 1 INTO x +', 'SELECT 1 INTO x,', 'SELECT 1 INTO x FROM',
            'SELECT 1 INTO x UNION SELECT', 'SELECT 1 INTO x FROM absent WHERE (',
            'SELECT 1 FROM absent INTO x +', 'SELECT SQL_CACHE SQL_CACHE 1 INTO x WHERE (',
            'SELECT 1 LIMIT x WHERE (', 'SELECT 1 INTO @x, y WHERE (',
            'CREATE PROCEDURE p() SELECT 1 INTO x WHERE (',
            'CREATE PROCEDURE p() SELECT 1 INTO @x WHERE ROW(1,2)', 'CALL p()',
            'DROP TABLE name.t', ...$writes,
            'DROP DATABASE name', 'DROP DATABASE d',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Complete retained writes before and after creating their target database and table
     */
    #[DataProvider('providerStatements')]
    public function testIntoDoesNotLoseTheOuterWriteWhenAFilterRequiresDeferredPreparation(string $release, array $statements): void
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
