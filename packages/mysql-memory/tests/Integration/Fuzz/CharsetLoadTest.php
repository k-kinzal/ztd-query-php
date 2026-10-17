<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class CharsetLoadTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = ['CREATE DATABASE d', 'USE d'];
            foreach (['CONVERT(1 USING ucs2)', 'CONVERT(1 USING utf8)', 'CONVERT(1 USING utf8mb3)', 'CONVERT(1 USING utf8mb4)', 'CAST(1 AS CHAR CHARACTER SET utf8)', 'CAST(1 AS CHAR CHARACTER SET ucs2)', "_utf8'x'", "_ucs2 X'0041'", 'CONVERT(1 USING ucs2), CONVERT((1,2) USING utf8)', 'CONVERT(1 USING utf8), CONVERT((1,2) USING ucs2)', 'CONVERT((1,2) USING utf8), CONVERT(1 USING ucs2)', 'CONVERT((1,2) USING utf8mb3)'] as $index => $expression) {
                $name = 'p' . $index;
                array_push($statements, 'SELECT ' . $expression, 'CREATE PROCEDURE ' . $name . '() BEGIN IF @run THEN SELECT ' . $expression . '; END IF; END', 'SET @run=0', 'CALL ' . $name . '()', 'CALL ' . $name . '()', 'ALTER PROCEDURE ' . $name . " COMMENT 'reload'", 'CALL ' . $name . '()', 'SET @run=1', 'CALL ' . $name . '()');
            }
            array_push($statements, 'SELECT CONVERT(1 USING utf8', 'SELECT CONVERT(1 USING ucs2', 'SELECT CONVERT(1 USING utf8,?', 'SELECT CONVERT(1 USING utf8),?', 'SELECT CHAR(65 USING utf8', 'SELECT CHAR(65 USING utf8),?', 'SELECT CAST(1 AS CHAR CHARACTER SET utf8', 'SELECT CAST(1 AS CHAR CHARACTER SET utf8),?');
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Typed literals, dependent operands and reached/skipped stored instructions
     */
    #[DataProvider('providerReleases')]
    public function testCharsetNoticesFollowDefinitionLoadingAndInvalidation(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $catalog = $native->query('SELECT COUNT(*) FROM mysql.time_zone_name');
            self::assertInstanceOf(mysqli_result::class, $catalog);
            $row = $catalog->fetch_row();
            self::assertIsArray($row);
            self::assertArrayHasKey(0, $row);
            self::assertIsString($row[0]);
            $server = Server::start($release, namedTimeZones: (int) $row[0] > 0);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

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
