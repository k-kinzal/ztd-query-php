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
final class NamesAssignmentTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'SET NAMES utf8mb4',
            'CREATE PROCEDURE p() SET NAMES = 1',
            'CREATE DATABASE d',
            'USE d',
            'SET NAMES = 1',
            'SET NAMES := missing_column',
            'SET NAMES = !1, @a = !2',
            'SET NAMES = 1, @@missing_var = 0',
            'SET @@missing_var=0, NAMES=1',
            'SET NAMES = 1, @a = DATE \'bad\'',
            'SET NAMES = DATE \'bad\', @a = !2',
            'SET NAMES = 1 TRAILING',
            'SET NAMES = 1, @a = \'unfinished',
            'SET NAMES = !1, @a = ?',
            'SET @a = \'NAMES = 1\', NAMES
/* marker */ := \'utf8\'',
            'SET NAMES = CAST(1 AT LOCAL AS DATETIME)',
            'CREATE PROCEDURE p() BEGIN DECLARE names INT; SET NAMES=1; END',
            'CREATE PROCEDURE p() BEGIN DECLARE names INT; SET NAMES = !1, @a=!2; END',
            'CREATE PROCEDURE p() BEGIN DECLARE names INT; DECLARE names INT; SET NAMES=1; END',
            'CREATE PROCEDURE p() BEGIN BEGIN DECLARE names INT; END; SET NAMES=1; END',
            'CREATE PROCEDURE p() BEGIN DECLARE names INT; BEGIN SET NAMES=1; END; END',
            'CREATE PROCEDURE p() BEGIN DECLARE `NAMÉS` INT; SET NAMES=1; END',
            'CREATE PROCEDURE p() BEGIN DECLARE names CONDITION FOR SQLSTATE \'45000\'; SET NAMES=1; END',
            'CREATE PROCEDURE p() BEGIN SET NAMES=1; SELECT BINARY 1; END',
            'CREATE PROCEDURE p() BEGIN DECLARE names INT; SET NAMES=1; SELECT \' END',
            'CREATE PROCEDURE p() BEGIN DECLARE names INT; SET NAMES=1, @a = \'unfinished END',
            'CREATE PROCEDURE p() BEGIN DECLARE names INT; SET NAMES = DATE \'bad\'; END',
            'CREATE PROCEDURE p(names INT) SET NAMES=1',
            'CREATE PROCEDURE p(names INT) BEGIN SET NAMES=1; END',
            'CREATE PROCEDURE p() BEGIN DECLARE names INT; SET `names` = 1; SELECT names; END',
            'CALL p()',
            'DROP PROCEDURE p',
            'SET NAMES utf8mb4',
            'SET CHARACTER SET DEFAULT',
            'SET @a=!2',
            'SET @a =!2',
            'SET @a= !2',
            'SELECT 1=!2',
            'SELECT 1 =!2',
            'SELECT 1= !2',
            'SHOW PARSE_TREE SET NAMES = !1',
            'CREATE PROCEDURE p() BEGIN DECLARE names INT; SET NAMES = !1, @a = !2; END',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Assignments, preceding conditions and enclosing program declarations
     */
    #[DataProvider('providerReleases')]
    public function testRejectedAssignmentsPreserveNativeConditionsAndLexicalScopes(string $release, array $statements): void
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
