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
final class ProgramIdentityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
    'SET NAMES utf8mb4',
    'CREATE DATABASE app',
    'USE app',
    'CREATE PROCEDURE p(`É` INT) SELECT e, `é`, `É`',
    'CALL p(7)',
    'DROP PROCEDURE p',
    'CREATE PROCEDURE p(`É` INT) BEGIN SET e=e+1; SELECT `é`; END',
    'CALL p(7)',
    'DROP PROCEDURE p',
    'CREATE PROCEDURE p() BEGIN DECLARE `É` INT DEFAULT 7; SET e=e+1; SELECT `é`; END',
    'CALL p()',
    'DROP PROCEDURE p',
    'CREATE PROCEDURE p(`É` INT) BEGIN DECLARE e INT DEFAULT 9; SELECT `é`; END',
    'CALL p(7)',
    'DROP PROCEDURE p',
    'CREATE PROCEDURE p(`É` INT) BEGIN SELECT 8 INTO e; SELECT `é`; END',
    'CALL p(7)',
    'DROP PROCEDURE p',
    'CREATE TABLE t(e INT, `É` INT)',
    'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.`É`=NEW.e+1',
    'INSERT INTO t VALUES(3,6)',
    'SELECT * FROM t',
    'DROP PROCEDURE IF EXISTS p',
    'CREATE PROCEDURE p(`É` INT) SELECT `É`, ((`É`)), +`É`, + ((`É`)), e, (e), +e, (+e), /*before*/`e`/*after*/, {t `e`}',
    'CALL p(7)',
    'DROP PROCEDURE p',
    "SET sql_mode='ANSI_QUOTES'",
    'CREATE PROCEDURE p("É" INT) SELECT "e", ( "e" ), + "e"',
    'CALL p(7)',
];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The command histories and competing operand failures
     */
    #[DataProvider('providerReleases')]
    public function testVariableIdentityAndObservableNamesMatchMySql(string $release, array $statements): void
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
