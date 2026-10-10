<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz\Process;

use Fuzz\Target\Process\Listing;
use Fuzz\Target\Process\Sample;
use Fuzz\Target\Servers;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class ListingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerStatements(): iterable
    {
        yield 'plain' => ['SHOW PROCESSLIST', true];
        yield 'full' => [" show\nfull processlist ; ", true];
        yield 'multiple' => ['SHOW PROCESSLIST; SELECT 1', false];
        yield 'filtered' => ["SHOW PROCESSLIST WHERE User='root'", false];
        yield 'unrelated' => ['SHOW STATUS', false];
    }

    #[DataProvider('providerStatements')]
    public function testHandlesOnlyCompleteIsolatedListings(string $sql, bool $handles): void
    {
        self::assertSame($handles, Listing::handles($sql));
    }

    /**
     * @return iterable<string, array{array<mixed>, Sample}>
     */
    public static function providerInvalidObservations(): iterable
    {
        $row = [7, 'root', 'localhost:1234', 'fz', 'Query', 100, 'init', 'SHOW PROCESSLIST'];
        $guard = [2, 'memory_guard', 'localhost:2345', null, 'Sleep', 0, '', null];
        $rows = [2 => $guard, 7 => $row];
        $after = new Sample(7, 101, 'OFF', $rows);
        yield 'missing query' => [[$guard], $after];
        yield 'missing guard' => [[$row], $after];
        yield 'extra row' => [[$guard, $row, [9, 'other', 'localhost:3456', null, 'Sleep', 0, '', null]], $after];
        yield 'duplicate row' => [[$guard, $row, $row], $after];
        foreach ([0 => 9, 2 => 'localhost:9999', 5 => 99] as $field => $value) {
            yield 'unrelated field ' . $field => [[$guard, array_replace($row, [$field => $value])], $after];
        }
        yield 'future elapsed time' => [[$guard, array_replace($row, [5 => 102])], $after];
        yield 'negative idle time' => [[array_replace($guard, [5 => -1]), $row], $after];
        yield 'another current identity' => [[$guard, $row], new Sample(8, 101, 'OFF', $rows)];
        yield 'missing daemon' => [[$guard, $row], new Sample(7, 101, 'ON', $rows)];
        yield 'lost row after observation' => [[$guard, $row], new Sample(7, 101, 'OFF', [7 => $row])];
        yield 'changed sampled port' => [[$guard, $row], new Sample(7, 101, 'OFF', [2 => $guard, 7 => array_replace($row, [2 => 'localhost:9999'])])];
    }

    /**
     * @param array<mixed> $rows
     */
    #[DataProvider('providerInvalidObservations')]
    public function testValidatedRetainsAnyUnverifiedObservation(array $rows, Sample $after): void
    {
        $before = new Sample(7, 100, 'OFF', [2 => [2, 'memory_guard', 'localhost:2345', null, 'Sleep', 0, '', null], 7 => [7, 'root', 'localhost:1234', 'fz', 'Query', 100, 'executing', 'SELECT 1']]);
        $columns = [['Id'], ['User'], ['Host'], ['db'], ['Command'], ['Time'], ['State'], ['Info']];
        $observation = ['results' => [['columns' => $columns, 'rows' => $rows]], 'warnings' => []];

        self::assertSame($observation, (new Listing($before, 2))->validated($observation, $after));
    }

    public function testRowsKeepsEveryStaticFieldAndChecksTheDaemon(): void
    {
        $rows = [2 => [2, 'memory_guard', 'localhost:2345', null, 'Sleep', 0, '', null], 5 => [5, 'event_scheduler', 'localhost', null, 'Daemon', 10, 'Waiting on empty queue', null], 7 => [7, 'root', 'localhost:1234', 'fz', 'Query', 100, 'init', 'SHOW PROCESSLIST']];
        $sample = new Sample(7, 100, 'ON', $rows);
        $marker = '{' . Listing::CONTRACT . ':';

        self::assertSame([
            [$marker . 'guard}', 'memory_guard', 'localhost:' . $marker . 'port}', null, 'Sleep', $marker . 'elapsed}', '', null],
            [$marker . 'daemon}', 'event_scheduler', 'localhost', null, 'Daemon', $marker . 'elapsed}', 'Waiting on empty queue', null],
            [$marker . 'current}', 'root', 'localhost:' . $marker . 'port}', 'fz', 'Query', $marker . 'elapsed}', 'init', 'SHOW PROCESSLIST'],
        ], (new Listing($sample, 2))->rows($rows, $sample));
        self::assertNull((new Listing($sample, 7))->rows($rows, $sample));
    }

    public function testValidatedPreservesMetadataWarningsAndOtherObservations(): void
    {
        $rows = [2 => [2, 'memory_guard', 'localhost:2345', null, 'Sleep', 0, '', null], 7 => [7, 'root', 'localhost:1234', 'fz', 'Query', 100, 'init', 'SHOW PROCESSLIST']];
        $sample = new Sample(7, 100, 'OFF', $rows);
        $columns = [['Id'], ['User'], ['Host'], ['db'], ['Command'], ['Time'], ['State'], ['Info']];
        $observation = ['results' => [['columns' => $columns, 'rows' => array_values($rows)]], 'warnings' => [['Warning', 1287, 'kept']], 'tables' => ['t1' => [[1]]]];
        $listing = new Listing($sample, 2);
        $result = $listing->validated($observation, $sample);

        self::assertSame([Listing::CONTRACT], $result['contracts']);
        self::assertIsArray($result['results']);
        self::assertIsArray($result['results'][0]);
        self::assertSame($columns, $result['results'][0]['columns']);
        self::assertSame($observation['warnings'], $result['warnings']);
        self::assertSame($observation['tables'], $result['tables']);
        self::assertSame(['error' => [1]], $listing->validated(['error' => [1]], $sample));
        self::assertSame(['results' => [[]]], $listing->validated(['results' => [[]]], $sample));
    }

    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function providerHosts(): iterable
    {
        yield 'valid source port' => ['localhost:65535', 'current', 'localhost:{' . Listing::CONTRACT . ':port}'];
        yield 'zero port' => ['localhost:0', 'current', null];
        yield 'oversized port' => ['localhost:65536', 'current', null];
        yield 'missing port' => ['localhost', 'current', null];
        yield 'daemon' => ['localhost', 'daemon', 'localhost'];
        yield 'daemon with port' => ['localhost:1234', 'daemon', null];
    }

    #[DataProvider('providerHosts')]
    public function testHostAcceptsOnlyTheValidatedPeer(string $host, string $role, ?string $expected): void
    {
        $row = [1, 'root', $host, null, 'Query', 0, '', null];
        $sample = new Sample(1, 0, 'OFF', [1 => $row]);

        self::assertSame($expected, (new Listing($sample, 2))->host($row, $row, $row, $role));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerListings(): iterable
    {
        yield 'plain' => ['SHOW PROCESSLIST'];
        yield 'full' => ['SHOW FULL PROCESSLIST'];
    }

    #[DataProvider('providerListings')]
    public function testComparableChecksEachServerIndependently(string $sql): void
    {
        [$target] = Servers::shared();
        $result = $target->compare($sql);

        self::assertFalse($result->volatile, (string) $result->referenceDifference);
        self::assertNull($result->difference, (string) $result->difference);
        self::assertSame([Listing::CONTRACT], $result->contracts);
    }

    public function testCaptureUsesTheRepairConnectionsOwnIdentity(): void
    {
        [$target] = Servers::shared();
        self::assertNull($target->compare('ALTER USER CURRENT_USER PASSWORD EXPIRE')->difference);
        $guard = $target->guard();
        $target->repair($guard);
        $pdo = $target->connect($target->native, $target->nativeUser, $target->nativePassword);
        $listing = Listing::capture($pdo, $guard);

        self::assertNotNull($listing);
        self::assertNotSame($listing->before->current, $listing->guard);
        self::assertArrayHasKey($listing->guard, $listing->before->rows);
    }

    public function testPrepareRestartsAnEnabledDaemonAfterTheOldEventIsRemoved(): void
    {
        [$target] = Servers::shared();
        $target->repair($target->guard());
        $pdo = $target->connect($target->native, $target->nativeUser, $target->nativePassword);
        $pdo->exec('SET @previous_scheduler=@@GLOBAL.event_scheduler');
        try {
            $pdo->exec('SET GLOBAL event_scheduler=ON');
            $pdo->exec('SET timestamp=0');
            $pdo->exec('CREATE EVENT later ON SCHEDULE AT CURRENT_TIMESTAMP + INTERVAL 1 DAY DO DO 1');
            $pdo->exec('DO SLEEP(0.1)');
            $pdo->exec('DROP EVENT later');
            Listing::prepare($pdo);
            $statement = $pdo->query("SELECT USER, COMMAND, STATE, @@GLOBAL.event_scheduler FROM information_schema.PROCESSLIST WHERE USER='event_scheduler'");
            self::assertNotFalse($statement);
            self::assertSame([['event_scheduler', 'Daemon', 'Waiting on empty queue', 'ON']], $statement->fetchAll(PDO::FETCH_NUM));
            $pdo->exec('SET GLOBAL event_scheduler=OFF');
            Listing::prepare($pdo);
            $setting = $pdo->query('SELECT @@GLOBAL.event_scheduler');
            self::assertNotFalse($setting);
            self::assertSame('OFF', $setting->fetchColumn());
        } finally {
            $pdo->exec('SET GLOBAL event_scheduler=@previous_scheduler');
        }
    }
}
