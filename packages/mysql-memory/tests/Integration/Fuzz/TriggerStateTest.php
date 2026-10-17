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
final class TriggerStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = ['CREATE TRIGGER x.tr BEFORE UPDATE ON t FOR EACH ROW BEGIN END', 'CREATE TRIGGER x.tr2 BEFORE UPDATE ON absent.t FOR EACH ROW BEGIN END', 'CREATE TRIGGER absent.tr BEFORE UPDATE ON x.t FOR EACH ROW BEGIN END', 'CREATE DATABASE x', 'CREATE TRIGGER x.tr BEFORE UPDATE ON t FOR EACH ROW BEGIN END', 'CREATE TRIGGER x.tr2 BEFORE UPDATE ON absent.t FOR EACH ROW BEGIN END', 'CREATE TRIGGER absent.tr BEFORE UPDATE ON x.t FOR EACH ROW BEGIN END', 'CREATE TABLE x.t(a INT)', 'CREATE TRIGGER x.tr BEFORE UPDATE ON t FOR EACH ROW BEGIN END', 'CREATE TRIGGER x.tr2 BEFORE UPDATE ON absent.t FOR EACH ROW BEGIN END', 'CREATE TRIGGER absent.tr BEFORE UPDATE ON x.t FOR EACH ROW BEGIN END', 'INSERT INTO x.t VALUES(1),(2)', 'CREATE TRIGGER x.tr BEFORE UPDATE ON t FOR EACH ROW BEGIN END', 'CREATE TRIGGER x.tr2 BEFORE UPDATE ON absent.t FOR EACH ROW BEGIN END', 'CREATE TRIGGER absent.tr BEFORE UPDATE ON x.t FOR EACH ROW BEGIN END', 'CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', 'CREATE VIEW v AS SELECT * FROM t', 'CREATE TRIGGER absent.tr2 BEFORE UPDATE ON v FOR EACH ROW BEGIN END', 'CREATE TRIGGER tr BEFORE UPDATE ON absent.t FOR EACH ROW BEGIN END'];
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Typed literals, dependent operands and reached/skipped stored instructions
     */
    #[DataProvider('providerReleases')]
    public function testTriggerCreationPreservesSchemaAndTableStateTransitions(string $release, array $statements): void
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
