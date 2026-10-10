<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class ConnectionCountersTest extends TestCase
{
    /**
     * @return iterable<string, array{bool}>
     */
    public static function providerServers(): iterable
    {
        yield 'MySQL' => [false];
        yield 'memory' => [true];
    }

    #[DataProvider('providerServers')]
    public function testMaximumSurvivesDisconnectAndFlushStartsAgain(bool $memory): void
    {
        [$target] = Servers::shared();
        $target->repair($memory ? $target->memoryGuard() : $target->guard());
        $connect = static fn (): PDO => new PDO($memory ? $target->memory : $target->native, $memory ? 'root' : $target->nativeUser, $memory ? '' : $target->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $counts = static function (PDO $pdo): array {
            $statement = $pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Connections','Threads_connected','Max_used_connections')");
            self::assertNotFalse($statement);
            $values = [];
            foreach ($statement->fetchAll(PDO::FETCH_KEY_PAIR) as $name => $value) {
                self::assertIsString($name);
                self::assertIsNumeric($value);
                $values[$name] = (int) $value;
            }

            return $values;
        };
        $first = $connect();
        $first->exec('FLUSH LOCAL STATUS');
        $before = $counts($first);
        $second = $connect();
        $third = $connect();
        $opened = $counts($first);
        self::assertSame($before['Connections'] + 2, $opened['Connections']);
        self::assertSame($before['Threads_connected'] + 2, $opened['Threads_connected']);
        self::assertSame($opened['Threads_connected'], $opened['Max_used_connections']);
        unset($second, $third);
        $closed = (static function () use ($counts, $first, $before): array {
            $deadline = microtime(true) + 3.0;
            do {
                $closed = $counts($first);
            } while ($closed['Threads_connected'] !== $before['Threads_connected'] && microtime(true) < $deadline);

            return $closed;
        })();
        self::assertSame($before['Threads_connected'], $closed['Threads_connected']);
        self::assertSame($opened['Max_used_connections'], $closed['Max_used_connections']);
        self::assertSame($opened['Connections'], $closed['Connections']);
        $first->exec('FLUSH LOCAL STATUS');
        $flushed = $counts($first);
        self::assertSame($before['Threads_connected'], $flushed['Max_used_connections']);
        self::assertSame($opened['Connections'], $flushed['Connections']);
    }

    public function testPeakTimeUsesTheRealClockAndTheReadingSessionZone(): void
    {
        [$target] = Servers::shared();
        $schema = str_starts_with($target->version, '5.6.') ? 'information_schema' : 'performance_schema';
        $read = "SELECT VARIABLE_VALUE FROM {$schema}.global_status WHERE VARIABLE_NAME='Max_used_connections_time'";
        $sql = "SET time_zone='+00:00', timestamp=1; SET @before=SYSDATE(); FLUSH LOCAL STATUS; "
            . "SELECT VARIABLE_VALUE BETWEEN @before AND SYSDATE() FROM {$schema}.global_status WHERE VARIABLE_NAME='Max_used_connections_time'; "
            . "SET @utc=({$read}); SET time_zone='+09:00'; SELECT TIMESTAMPDIFF(HOUR,@utc,({$read})); "
            . "SELECT VARIABLE_VALUE=({$read}) FROM {$schema}.session_status WHERE VARIABLE_NAME='Max_used_connections_time'";
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }

    /**
     * @return iterable<string, array{bool, string, string}>
     */
    public static function providerDaemons(): iterable
    {
        yield 'MySQL stopped' => [false, 'OFF', '1'];
        yield 'MySQL running' => [false, 'ON', '2'];
        yield 'memory stopped' => [true, 'OFF', '1'];
        yield 'memory running' => [true, 'ON', '2'];
    }

    #[DataProvider('providerDaemons')]
    public function testRunningThreadsIncludesTheEnabledEventDaemon(bool $memory, string $setting, string $expected): void
    {
        [$target] = Servers::shared();
        $target->repair($memory ? $target->memoryGuard() : $target->guard());
        $pdo = new PDO($memory ? $target->memory : $target->native, $memory ? 'root' : $target->nativeUser, $memory ? '' : $target->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $read = $pdo->query('SELECT @@GLOBAL.event_scheduler');
        self::assertNotFalse($read);
        $original = $read->fetchColumn();
        self::assertIsString($original);
        try {
            $pdo->exec("SET GLOBAL event_scheduler='{$setting}'");
            $actual = (static function () use ($pdo, $expected): string {
                $deadline = microtime(true) + 3.0;
                do {
                    $read = $pdo->query("SHOW GLOBAL STATUS LIKE 'Threads_running'");
                    self::assertNotFalse($read);
                    $actual = $read->fetchColumn(1);
                    self::assertIsString($actual);
                } while ($actual !== $expected && microtime(true) < $deadline);

                return $actual;
            })();
            self::assertSame($expected, $actual);
        } finally {
            $pdo->exec('SET GLOBAL event_scheduler=' . $pdo->quote($original));
        }
    }
}
