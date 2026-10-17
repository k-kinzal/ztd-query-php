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
final class ConditionDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            "SIGNAL SQLSTATE '00000'",
            "SIGNAL SQLSTATE '00123'",
            "SIGNAL SQLSTATE '4500'",
            "SIGNAL SQLSTATE '4500a'",
            "RESIGNAL SQLSTATE '450001'",
            "SIGNAL SQLSTATE '00000' SET MESSAGE_TEXT = DATE 'bad'",
            "SIGNAL SQLSTATE '00000' SET MESSAGE_TEXT = @@unknown_var",
            "SIGNAL SQLSTATE '00000' SET MESSAGE_TEXT = +",
            "CREATE PROCEDURE p() SIGNAL SQLSTATE '00000'",
            "CREATE PROCEDURE p() BEGIN SELECT DATE 'bad'; SIGNAL SQLSTATE '00000'; END",
            "CREATE PROCEDURE d.p() BEGIN CALL q(); SIGNAL SQLSTATE '00000'; END",
            'CREATE DATABASE d',
            'USE d',
            "CREATE PROCEDURE p() SIGNAL SQLSTATE '00000'",
            "CREATE PROCEDURE p() BEGIN SELECT BINARY 1; SIGNAL SQLSTATE '00000'; END",
            "CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '00000'; END",
            "CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '00000' BEGIN END; END",
            "CREATE PROCEDURE p() BEGIN SELECT SQL_NO_CACHE 1; SIGNAL SQLSTATE '00000'; END",
            "CREATE PROCEDURE p() BEGIN SIGNAL SQLSTATE '00000'; SELECT DATE 'bad'; END",
            "CREATE PROCEDURE p() BEGIN SELECT DATE 'bad'; SIGNAL SQLSTATE '00000'; END",
            "CREATE PROCEDURE p() BEGIN DECLARE x INT CHARACTER SET bad; SIGNAL SQLSTATE '00000'; END",
            "CREATE PROCEDURE p() BEGIN SIGNAL missing_condition; SIGNAL SQLSTATE '00000'; END",
            "CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE x INT; SIGNAL SQLSTATE '00000'; END",
            "CREATE PROCEDURE p() BEGIN SELECT @@missing_variable; SIGNAL SQLSTATE '00000'; END",
            "CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; SIGNAL c; SIGNAL SQLSTATE '00000'; END",
            "CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; BEGIN SIGNAL c; SIGNAL SQLSTATE '00000'; END; END",
            "CREATE PROCEDURE p() BEGIN BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; END; SIGNAL c; SIGNAL SQLSTATE '00000'; END",
            "CREATE PROCEDURE p(x INT, x INT) SIGNAL SQLSTATE '00000'",
            "SIGNAL SQLSTATE 'A\\0AAA'",
            "SIGNAL SQLSTATE ''",
            "SIGNAL SQLSTATE '45猫00'",
            "SIGNAL SQLSTATE '45000'",
            "SIGNAL SQLSTATE '01000'",
            "RESIGNAL SQLSTATE '02000'",
            'SET NAMES utf8mb4',
            'SET character_set_results = latin1',
            "SIGNAL SQLSTATE 'é猫Ω😀'",
            'SET character_set_results = ascii',
            "SIGNAL SQLSTATE 'é猫Ω😀'",
            'SET character_set_results = utf8mb4',
            "SIGNAL SQLSTATE 'é猫Ω😀'",
            'SET character_set_results = binary',
            "SIGNAL SQLSTATE 'é猫Ω😀'",
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The ordered condition inputs and database changes
     */
    #[DataProvider('providerReleases')]
    public function testConditionDomainAndDiagnosticOrderMatchMySql(string $release, array $statements): void
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
