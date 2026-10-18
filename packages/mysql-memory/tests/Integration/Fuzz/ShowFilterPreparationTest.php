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
final class ShowFilterPreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [];
        foreach (['', 'CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)'] as $setup) {
            if ($setup !== '') {
                $statements[] = $setup;
            }
            foreach (['SHOW TABLE STATUS FROM missing', 'SHOW TABLE STATUS FROM d', 'SHOW TABLE STATUS', 'SHOW FULL TABLES FROM missing', 'SHOW TRIGGERS FROM missing', 'SHOW EVENTS FROM missing'] as $head) {
                foreach (['WHERE ROW(1,2) LIKE 1', 'WHERE ROW(1,2) LIKE (SELECT x FROM absent)', 'WHERE (SELECT x FROM absent)', 'WHERE DENSE_RANK() OVER w', 'WHERE missingcolumn=1'] as $filter) {
                    $statements[] = $head . ' ' . $filter;
                }
            }
        }
        foreach (['WHERE 0', 'WHERE NULL', "WHERE `Database`='d'", "WHERE `Database`='missing'", "LIKE 'd'", "LIKE 'missing'", 'WHERE LENGTH(`Database`)=0'] as $filter) {
            $statements[] = 'SHOW DATABASES ' . $filter;
        }
        $statements[] = (string) file_get_contents(__DIR__ . '/fixtures/show-refused-filter.sql');
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements SHOW filters across catalog states
     */
    #[DataProvider('providerReleases')]
    public function testShowFiltersPreserveTheirInspectionAndDependencyOrder(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: $prepared && str_starts_with($sql, 'SHOW'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: $prepared && str_starts_with($sql, 'SHOW'))), $statements);

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
