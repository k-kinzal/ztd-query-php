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
final class ErrorCodeDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 0; END',
            'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0; SELECT +; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 0; SELECT +; END',
            'CREATE DATABASE d',
            'USE d',
        ];
        foreach (['0', '000', '0.7', '.5', '0e9', '0x00', "x''", '1', '4294967296', '18446744073709551616', '0x100000000'] as $code) {
            $statements[] = 'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR ' . $code . '; END';
            if (!in_array($code, ['0', '000', '0.7', '.5', '0e9', '0x00', "x''"], true)) {
                $statements[] = 'CALL p()';
                $statements[] = 'DROP PROCEDURE p';
            }
        }
        foreach ([
            'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0; SELECT +; END',
            "CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0; SIGNAL SQLSTATE '00000'; END",
            "CREATE PROCEDURE p() BEGIN SIGNAL SQLSTATE '00000'; BEGIN DECLARE c CONDITION FOR 0; END; END",
            'CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE x INT; DECLARE c CONDITION FOR 0; END',
            'CREATE PROCEDURE p() BEGIN SELECT BINARY 1; BEGIN DECLARE c CONDITION FOR 0; END; END',
            'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0; SELECT BINARY 1; END',
            "CREATE PROCEDURE p() BEGIN SELECT DATE 'bad'; BEGIN DECLARE c CONDITION FOR 0; END; END",
            "CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0; SELECT DATE 'bad'; END",
            'CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR 0 BEGIN END; END',
            'CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR 0 BEGIN SELECT +; END; END',
            'CREATE PROCEDURE p() BEGIN SIGNAL missing; BEGIN DECLARE c CONDITION FOR 0; END; END',
        ] as $sql) {
            $statements[] = $sql;
        }
        foreach (['1e2', '1.9e2', '0x01', '4294967297'] as $code) {
            $statements[] = 'CREATE PROCEDURE handled() BEGIN DECLARE c CONDITION FOR ' . $code . "; DECLARE CONTINUE HANDLER FOR c SET @handled=1; SET @handled=0; SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO=1; SET @continued=1; END";
            $statements[] = 'CALL handled()';
            $statements[] = 'SELECT @handled,@continued';
            $statements[] = 'DROP PROCEDURE handled';
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The ordered condition inputs and database changes
     */
    #[DataProvider('providerReleases')]
    public function testNumericConditionDomainsAndDiagnosticOrderMatchMySql(string $release, array $statements): void
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
