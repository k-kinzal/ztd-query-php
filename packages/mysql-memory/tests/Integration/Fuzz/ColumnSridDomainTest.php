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
final class ColumnSridDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = [(string) file_get_contents(__DIR__ . '/fixtures/column-srid-overflow.sql')];
            foreach (['0', '4326', '4294967295', '4294967296', '000000014266163218578085946', '0x100000000', '18446744073709551616', '1.5'] as $number) {
                foreach (['INT', 'POINT'] as $type) {
                    foreach (["CREATE TABLE t(a $type SRID $number)", "CREATE TABLE absent.t(a $type SRID $number SRID 0)", "ALTER TABLE t ADD b $type SRID $number", "CREATE PROCEDURE test.p() IF @run THEN CREATE TABLE t(a $type SRID $number); END IF"] as $sql) {
                        $statements[] = $sql;
                        $statements[] = 'DROP PROCEDURE IF EXISTS test.p';
                    }
                }
            }
            yield $release => [$release, [...$statements, 'CREATE DATABASE d', 'USE d', ...$statements]];
        }
    }

    /**
     * @param list<string> $statements Boundary identifiers and repeated catalog states
     */
    #[DataProvider('providerInputs')]
    public function testIntrinsicRangePrecedesColumnCompatibilityAndCatalogLookup(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $native->exec('CREATE DATABASE IF NOT EXISTS test');
                $memory->exec('CREATE DATABASE IF NOT EXISTS test');
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
