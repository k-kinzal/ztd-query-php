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
final class UntilOperandsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $legacy = version_compare($release, '8.0', '<');
            $verb = $legacy ? 'START SLAVE' : 'START REPLICA';
            $file = $legacy ? 'MASTER_LOG_FILE' : 'SOURCE_LOG_FILE';
            $position = $legacy ? 'MASTER_LOG_POS' : 'SOURCE_LOG_POS';
            $clauses = [$file . "='a'", $position . '=4', 'SQL_AFTER_MTS_GAPS, ' . $file . "='a', " . $position . '=4', "SQL_AFTER_GTIDS='', " . $file . "='a', " . $position . '=4'];
            $statements = array_map(static fn (string $clause): string => $verb . ' UNTIL ' . $clause, $clauses);
            if (version_compare($release, '8.1', '>=')) {
                $statements = [...$statements, ...array_map(static fn (string $sql): string => 'SHOW PARSE_TREE ' . $sql, $statements)];
            }
            $bad = $verb . ' UNTIL ' . $file . "='a', " . $position . '=4';
            $applier = $verb . ' SQL_THREAD UNTIL ' . $file . "='binlog.000001', " . $position . '=4';
            $stop = $legacy ? 'STOP SLAVE' : 'STOP REPLICA';
            $statements = [...$statements, $bad, $applier, $legacy ? "CHANGE MASTER TO MASTER_HOST='a'" : "CHANGE REPLICATION SOURCE TO SOURCE_HOST='a'", $bad, $applier, $stop, $legacy ? 'RESET SLAVE ALL' : 'RESET REPLICA ALL', $bad];
            $statements = [...$statements, $verb . ' SQL_THREAD UNTIL SQL_AFTER_MTS_GAPS', 'SELECT SLEEP(0.05)', $stop, $verb . " SQL_THREAD UNTIL SQL_AFTER_GTIDS=''", 'SELECT SLEEP(0.05)', $stop, $verb . " SQL_THREAD UNTIL SQL_BEFORE_GTIDS=''", $stop];
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testUntilCombinationsAndConfigurationTransitionsMatchNative(string $release, array $statements): void
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
