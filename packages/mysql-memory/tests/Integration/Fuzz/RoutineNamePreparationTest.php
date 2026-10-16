<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class RoutineNamePreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'CREATE TABLE d.t(a INT)'];
        foreach (['', 'bad ', str_repeat('p', 65)] as $name) {
            foreach (['CREATE PROCEDURE `%s`() DO 0', 'CREATE FUNCTION `%s`() RETURNS INT DETERMINISTIC RETURN 0', 'CREATE TRIGGER `%s` BEFORE INSERT ON d.t FOR EACH ROW SET @x=1', 'CREATE EVENT `%s` ON SCHEDULE EVERY 1 DAY DO DO 0', 'CALL `%s`()', 'DROP PROCEDURE `%s`', 'DROP FUNCTION `%s`', 'DROP TRIGGER `%s`', 'DROP EVENT `%s`', "ALTER PROCEDURE `%s` COMMENT 'x'", 'ALTER EVENT `%s` DISABLE', 'SHOW CREATE PROCEDURE `%s`', 'SHOW CREATE FUNCTION `%s`', 'SHOW CREATE TRIGGER `%s`', 'SHOW CREATE EVENT `%s`'] as $pattern) {
                $statements[] = sprintf($pattern, $name);
            }
        }
        $statements = [...$statements, 'CREATE PROCEDURE ``() DO ABS()', 'CREATE PROCEDURE ``() SELECT', 'CREATE TRIGGER `` BEFORE INSERT ON d.t FOR EACH ROW DO ABS()', 'CREATE TRIGGER `` BEFORE INSERT ON d.t FOR EACH ROW SELECT', 'CREATE EVENT `` ON SCHEDULE EVERY ABS() DAY DO DO 0', 'CREATE PROCEDURE p() DO 0', 'USE d', 'CREATE PROCEDURE p() DO 0', 'CALL p()', 'DROP PROCEDURE p', 'CALL p()'];
        $statements[] = 'DROP FUNCTION d.`' . str_repeat('p', 65) . '`';
        $statements[] = 'DROP FUNCTION `' . str_repeat('d', 65) . '`.f';
        $statements[] = 'DROP FUNCTION IF EXISTS `' . str_repeat('d', 65) . '`.f';
        foreach ([str_repeat('é', 64), "p\t"] as $name) {
            $statements[] = "CREATE PROCEDURE `$name`() DO 0";
            $statements[] = "CALL `$name`()";
            $statements[] = "DROP PROCEDURE `$name`";
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testRoutineNamesAreCheckedBeforeDatabaseAndBodyResolution(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, substr_count($sql, '?'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, substr_count($sql, '?'))), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
