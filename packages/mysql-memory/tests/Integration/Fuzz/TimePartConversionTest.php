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
final class TimePartConversionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['SET NAMES latin1'];
        foreach (["'x'", "''", 'NULL', "'12:99:00'", "'2001-01-01'", "'2001-02-30'", "'2001-01-01 04:05:06'", "'900:00:00'", '9000000', '123456', "'12:34:56x'", 'TRUE', "CAST('2001-01-01' AS DATE)"] as $value) {
            $columns = array_map(static fn (string $name): string => $name . '(' . $value . ') AS ' . strtolower($name), ['HOUR', 'MINUTE', 'SECOND', 'MICROSECOND']);
            $statements[] = 'SELECT ' . implode(', ', $columns);
        }
        foreach (["' '", "'  '", "'\t'", "'\n'", "' x'", "'x '", "' 12:00:00'", "'12:00:00 '", "'2001-01-01  12:00:00'", "'-'", "'+'", "'-x'", "'+1'", "'.1'", "'x1'", "'0x12'", "'1e2'", "'12:'", "'::'", "'0000-00-00'", "'2001-02-30 04:05:06'"] as $value) {
            $statements[] = 'SELECT HOUR(' . $value . ') AS h, CAST(' . $value . ' AS TIME) AS t';
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Temporal functions, casts, invalid values and delimiter notices
     */
    #[DataProvider('providerReleases')]
    public function testClockConversionsPreserveValuesAndOrderedWarnings(string $release, array $statements): void
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
