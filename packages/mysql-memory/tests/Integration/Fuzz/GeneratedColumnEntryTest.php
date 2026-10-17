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
final class GeneratedColumnEntryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        yield '5.7.44' => ['5.7.44', [
            'CREATE PROCEDURE p() PARSE_GCOL_EXPR(1)',
            'CREATE PROCEDURE p() BEGIN PARSE_GCOL_EXPR(1); END',
            'PARSE_GCOL_EXPR(1)',
            'PARSE_GCOL_EXPR(missing)',
            "PARSE_GCOL_EXPR(DATE 'invalid')",
            'PARSE_GCOL_EXPR(?)',
            'PARSE_GCOL_EXPR(',
            'PARSE_GCOL_EXPR BROKEN',
            "PARSE_GCOL_EXPR 'unclosed",
            '/* preceding */ PARSE_GCOL_EXPR(1)',
            "/* preceding\ncomment */\nPARSE_GCOL_EXPR(1)",
            'CREATE DATABASE entries',
            'USE entries',
            'CREATE PROCEDURE p() PARSE_GCOL_EXPR(1)',
            'CREATE PROCEDURE p() BEGIN PARSE_GCOL_EXPR(1); END',
            'CREATE PROCEDURE p() BEGIN PARSE_GCOL_EXPR BROKEN',
            'CREATE PROCEDURE p() BEGIN DECLARE c INT; PARSE_GCOL_EXPR(1); END',
            'CREATE PROCEDURE p() BEGIN DECLARE c INT; DECLARE c INT; PARSE_GCOL_EXPR(1); END',
            'CREATE TABLE t (a INT, b INT GENERATED ALWAYS AS (a+1))',
            'SELECT * FROM t',
            "PARSE_GCOL_EXPR(PASSWORD('x'))",
            'PARSE_GCOL_EXPR(1) TRAILING',
            "PARSE_GCOL_EXPR(1) 'unclosed",
            'CREATE PROCEDURE p() BEGIN PARSE_GCOL_EXPR(1); SELECT SQL_CACHE 1; END',
            'CREATE PROCEDURE p() BEGIN PARSE_GCOL_EXPR(1); SELECT FROM',
            'CREATE PROCEDURE p() BEGIN SELECT SQL_CACHE 1; PARSE_GCOL_EXPR(1); END',
            "CREATE PROCEDURE p() BEGIN DECLARE v DATE DEFAULT 'bad'; PARSE_GCOL_EXPR(1); END",
            'CREATE PROCEDURE p() BEGIN DECLARE v INT; PARSE_GCOL_EXPR((SELECT 1 INTO absent)); END',
            "CREATE PROCEDURE p() BEGIN DECLARE c INT; DECLARE c INT; PARSE_GCOL_EXPR(1); 'unclosed",
            "PREPARE p FROM 'PARSE_GCOL_EXPR(1)'",
            'PARSE_GCOL_EXPR((SELECT a FROM .t))',
            'INSERT INTO t(a) VALUES (7)',
            'SELECT * FROM t',
        ]];
    }

    /**
     * @param list<string> $statements Internal entries, preceding conditions and ordinary generated columns
     */
    #[DataProvider('providerReleases')]
    public function testInternalEntryAndGeneratedColumnsMatchNative(string $release, array $statements): void
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
