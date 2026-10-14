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
final class RoutineParametersTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'SET NAMES utf8mb4',
 'CREATE PROCEDURE p(a INT, a INT) BEGIN END',
 'CREATE PROCEDURE app.p(a INT, a INT) BEGIN END',
 'CREATE DATABASE app', 'USE app',
 'CREATE PROCEDURE p(a INT, a INT) BEGIN END',
 'CREATE PROCEDURE p(a INT, A INT) BEGIN END',
 'CREATE PROCEDURE p(`é` INT, `É` INT) BEGIN END',
 'CREATE PROCEDURE p(`é` INT, `e` INT) BEGIN END',
 'CREATE PROCEDURE p(`x ` INT, `x` INT) BEGIN END',
 'DROP PROCEDURE IF EXISTS p',
 'CREATE PROCEDURE p(a FLOAT(1,1), a INT) BEGIN END',
 'CREATE PROCEDURE p(a INT, a FLOAT(1,1)) BEGIN END',
 'CREATE PROCEDURE p(a INT, a INT) nonsense',
 'CREATE PROCEDURE p(a INT, a VARCHAR) BEGIN END',
 'CREATE PROCEDURE p(a INT, a INT COLLATE missing) BEGIN END',
 'CREATE PROCEDURE p(a INT COLLATE missing, a INT) BEGIN END',
 'CREATE FUNCTION f(a INT, a INT) RETURNS INT RETURN 1',
 'CREATE FUNCTION f(a INT, a INT) RETURNS VARCHAR RETURN 1',
 "CREATE PROCEDURE p(a INT, a INT, b INT) BEGIN SIGNAL SQLSTATE '00000'; END",
];
        foreach (['', ' ', ' x', 'x ', "x\t", "x\n", "x\r", "x\u{00a0}", "x\u{3000}", str_repeat('a', 64), str_repeat('a', 65), str_repeat('猫', 64), str_repeat('猫', 65)] as $name) {
            $quoted = '`' . $name . '`';
            array_push($statements, 'CREATE PROCEDURE p(' . $quoted . ' INT) BEGIN END', 'DROP PROCEDURE IF EXISTS p', 'CREATE PROCEDURE p(' . $quoted . ' FLOAT(1,1)) nonsense');
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The command histories and competing operand failures
     */
    #[DataProvider('providerReleases')]
    public function testParameterDomainsAndDiagnosticOrderMatchMySql(string $release, array $statements): void
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
