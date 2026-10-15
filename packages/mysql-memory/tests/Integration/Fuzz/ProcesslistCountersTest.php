<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use Fuzz\Target\Status\Listing;
use Fuzz\Target\Status\Snapshot;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Containers\PortStrategy\StaticPortStrategy;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ProcesslistCountersTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, int, ?int}>, DisposableMySql, DisposableMySql}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.3.0', '8.4.7', '9.1.0'] as $release) {
            $first = new DisposableMySql($release);
            $second = new DisposableMySql($release);
            $port = filter_var(getenv('MYSQL_MEMORY_STATUS_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65534]]);
            if (is_int($port)) {
                $first->withPortStrategy(new StaticPortStrategy($port));
                $second->withPortStrategy(new StaticPortStrategy($port + 1));
            }
            yield $release => [$release, [
            ['DO 0', 0, null],
            ['SELECT COUNT(*) FROM information_schema.processlist WHERE 0', 0, null],
            ['SELECT * FROM information_schema.processlist LIMIT 0', 0, null],
            ['SELECT COUNT(*) FROM information_schema.processlist WHERE ID=-1', 0, null],
            ['SELECT p.ID FROM information_schema.processlist p JOIN information_schema.processlist q ON 0', 0, null],
            ['EXPLAIN SELECT * FROM information_schema.processlist', 0, null],
            ['SELECT missing FROM information_schema.processlist', 0, 1054],
            ['SELECT COUNT(*) FROM information_schema.processlist', 1, null],
            ['SELECT p.ID FROM information_schema.processlist p JOIN information_schema.processlist q ON p.ID=q.ID', 2, null],
            ['SELECT COUNT(*) FROM information_schema.processlist UNION ALL SELECT COUNT(*) FROM information_schema.processlist', 2, null],
            ["PREPARE stmt FROM 'SELECT COUNT(*) FROM information_schema.processlist'", 0, null],
            ['EXECUTE stmt', 1, null],
            ['EXECUTE stmt', 1, null],
            ['DEALLOCATE PREPARE stmt', 0, null],
            ['SELECT COUNT(*) FROM performance_schema.processlist', 0, null],
            ['FLUSH STATUS', 0, null],
            ], $first, $second];
        }
    }

    /**
     * @param list<array{string, int, ?int}> $history SQL, materialized occurrences and expected errors
     */
    #[DataProvider('providerReleases')]
    public function testHistoryAndClockAgreeWithIndependentInstances(string $release, array $history, DisposableMySql $firstDefinition, DisposableMySql $secondDefinition): void
    {
        $first = Testcontainers::run($firstDefinition);
        $server = null;
        try {
            $second = Testcontainers::run($secondDefinition);
            try {
                $endpoints = [$first->getData(Endpoint::class), $second->getData(Endpoint::class)];
                $connections = array_map(static fn (Endpoint $endpoint): PDO => new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]), $endpoints);
                $prior = $connections[0]->query('SELECT COUNT(*) FROM information_schema.processlist');
                self::assertNotFalse($prior);
                $prior->fetchAll();
                $server = Server::start($release);
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observations = array_map(static function (PDO $connection) use ($memory, $history): array {
                    $connection->exec('SET NAMES utf8mb4');
                    $connection->exec('SET timestamp=1700000000');
                    array_map(static function (array $step) use ($connection): void {
                        [$sql, $increment, $error] = $step;
                        $before = Snapshot::read($connection, "SHOW GLOBAL STATUS LIKE 'Deprecated_use_i_s_processlist%'");
                        $start = $connection->query('SELECT UNIX_TIMESTAMP(SYSDATE(6))*1000000');
                        self::assertNotFalse($start);
                        $low = (int) $start->fetchColumn();
                        $observation = (new Observer())->observe($connection, $sql, true);
                        $failure = $observation['error'] ?? [];
                        self::assertIsArray($failure);
                        self::assertSame($error, $failure[0] ?? null, $sql);
                        $end = $connection->query('SELECT UNIX_TIMESTAMP(SYSDATE(6))*1000000');
                        self::assertNotFalse($end);
                        $after = Snapshot::read($connection, "SHOW GLOBAL STATUS LIKE 'Deprecated_use_i_s_processlist%'");
                        self::assertNotNull($before);
                        self::assertNotNull($after);
                        self::assertSame((int) $before['Deprecated_use_i_s_processlist_count'] + $increment, (int) $after['Deprecated_use_i_s_processlist_count'], $sql);
                        $clock = (int) $after['Deprecated_use_i_s_processlist_last_timestamp'];
                        self::assertGreaterThanOrEqual($increment === 0 ? (int) $before['Deprecated_use_i_s_processlist_last_timestamp'] : $low, $clock, $sql);
                        self::assertLessThanOrEqual($increment === 0 ? (int) $before['Deprecated_use_i_s_processlist_last_timestamp'] : (int) $end->fetchColumn(), $clock, $sql);
                    }, $history);
                    return array_map(static function (string $sql) use ($connection, $memory): string {
                        $listing = Listing::capture($connection, $sql);
                        self::assertNotNull($listing);
                        $result = $listing->comparable((new Observer())->observe($connection, $sql, true), $connection === $memory);
                        self::assertSame([Listing::CONTRACT], $result['contracts'] ?? null);
                        return serialize($result);
                    }, ['SHOW STATUS', 'SHOW GLOBAL STATUS', 'SHOW LOCAL STATUS', 'SHOW SESSION STATUS']);
                }, [...$connections, $memory]);
                self::assertSame($observations[0], $observations[1], 'Independent native instances');
                self::assertSame($observations[0], $observations[2], 'Memory');
            } finally {
                $second->stop();
            }
        } finally {
            $server?->stop();
            $first->stop();
        }
    }
}
