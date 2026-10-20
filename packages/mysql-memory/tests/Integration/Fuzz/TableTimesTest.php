<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\TableTimes;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class TableTimesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerStatements(): iterable
    {
        yield 'canonical inspection' => ['SHOW TABLE STATUS', '8.4.7', true];
        yield 'whitespace and terminator' => [" show\n table status ; ", '8.0.44', true];
        yield 'legacy creation and update clocks' => ['SHOW TABLE STATUS', '5.7.44', true];
        yield 'legacy creation clock with null update' => ['SHOW TABLE STATUS', '5.6.51', true];
        yield 'other database' => ['SHOW TABLE STATUS FROM sys', '8.4.7', false];
        yield 'arbitrary predicate' => ['SHOW TABLE STATUS WHERE some_function()', '8.4.7', false];
        yield 'preceding write' => ['INSERT INTO t1 VALUES(9); SHOW TABLE STATUS', '8.4.7', false];
        yield 'following statement' => ['SHOW TABLE STATUS; SELECT 1', '8.4.7', false];
        yield 'scalar expression' => ['SELECT UPDATE_TIME FROM information_schema.TABLES', '8.4.7', false];
    }

    #[DataProvider('providerStatements')]
    public function testHandlesOnlyThePlainInspection(string $sql, string $version, bool $handled): void
    {
        self::assertSame($handled, TableTimes::handles($sql, $version));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function providerTimes(): iterable
    {
        yield 'lower bound' => ['2026-10-10 01:00:00', true];
        yield 'inside interval' => ['2026-10-10 01:00:01', true];
        yield 'upper bound' => ['2026-10-10 01:00:02', true];
        yield 'observed previous second' => ['2026-10-10 00:59:59', true];
        yield 'two seconds before insertion' => ['2026-10-10 00:59:58', false];
        yield 'after insertion' => ['2026-10-10 01:00:03', false];
        yield 'pinned statement clock' => ['2023-11-14 22:13:20', false];
        yield 'missing update time' => [null, false];
        yield 'wrong representation' => [1791594001, false];
        yield 'malformed text within lexical bounds' => ['2026-10-10 01:00:01x', false];
    }

    #[DataProvider('providerTimes')]
    public function testComparableValidatesEveryUpdateAgainstItsOwnInsertion(mixed $time, bool $valid): void
    {
        $clock = new TableTimes();
        $clock->intervals = ['t1' => ['2026-10-10 01:00:00', '2026-10-10 01:00:02'], 't2' => ['2026-10-10 01:00:04', '2026-10-10 01:00:04']];
        $columns = [12 => ['Update_time', 'TABLES', 'DATETIME', 19, 0, []]];
        $observation = ['results' => [['columns' => $columns, 'rows' => [[0 => 't1', 12 => $time], [0 => 't2', 12 => '2026-10-10 01:00:04']]]], 'warnings' => [], 'tables' => ['t1' => [[1]]]];

        $comparable = $clock->comparable($observation);

        self::assertSame($valid, $comparable !== $observation);
        self::assertSame($valid ? [TableTimes::CACHED_UPDATE] : null, $comparable['contracts'] ?? null);
        self::assertIsArray($comparable['results']);
        self::assertIsArray($comparable['results'][0]);
        self::assertSame($columns, $comparable['results'][0]['columns']);
        self::assertSame($observation['tables'], $comparable['tables']);
        self::assertSame($observation['warnings'], $comparable['warnings']);
        self::assertSame($time, $observation['results'][0]['rows'][0][12]);
    }

    public function testComparableDoesNotAcceptDuplicateTablesOrAnUnsampledTable(): void
    {
        $clock = new TableTimes();
        $clock->intervals = ['t1' => ['2026-10-10 01:00:00', '2026-10-10 01:00:00']];
        $row = [0 => 't1', 12 => '2026-10-10 01:00:00'];
        $observation = ['results' => [['columns' => [12 => ['Update_time', 'TABLES', 'DATETIME']], 'rows' => [$row, $row]]]];
        $unsampled = $observation;
        $unsampled['results'][0]['rows'][1][0] = 't2';

        self::assertSame($observation, $clock->comparable($observation));
        self::assertSame($unsampled, $clock->comparable($unsampled));
    }

    public function testComparableLeavesErrorsAndUnrelatedMetadataExact(): void
    {
        $clock = new TableTimes();
        $error = ['error' => [1105, 'HY000', 'failure'], 'results' => [], 'warnings' => [['Error', 1105, 'failure']]];
        $other = ['results' => [['columns' => [12 => ['Update_time', 'some_table', 'DATETIME']], 'rows' => [[0 => 't1', 12 => '2026-10-10 01:00:00'], [0 => 't2', 12 => '2026-10-10 01:00:00']]]]];

        self::assertSame($error, $clock->comparable($error));
        self::assertSame($other, $clock->comparable($other));
    }

    public function testComparableRejectsAnInvalidCalendarDateWithinTheTextualBounds(): void
    {
        $clock = new TableTimes();
        $clock->intervals = ['t1' => ['2026-10-31 00:00:00', '2026-11-01 00:00:00'], 't2' => ['2026-11-01 00:00:00', '2026-11-01 00:00:00']];
        $observation = ['results' => [['columns' => [12 => ['Update_time', 'TABLES', 'DATETIME']], 'rows' => [[0 => 't1', 12 => '2026-10-32 00:00:00'], [0 => 't2', 12 => '2026-11-01 00:00:00']]]]];

        self::assertSame($observation, $clock->comparable($observation));
    }

    public function testContainsRejectsMissingReversedAndInvalidIntervals(): void
    {
        $clock = new TableTimes();
        $clock->intervals = ['t1' => ['2026-10-31 00:00:00', '2026-11-01 00:00:00'], 't2' => ['2026-11-01 00:00:01', '2026-11-01 00:00:00']];

        self::assertTrue($clock->contains('t1', '2026-10-31 23:59:59'));
        self::assertFalse($clock->contains('t1', '2026-10-32 00:00:00'));
        self::assertFalse($clock->contains('t2', '2026-11-01 00:00:00'));
        self::assertFalse($clock->contains('missing', '2026-11-01 00:00:00'));
    }

    public function testContainsBoundsLegacyInsertionLagWithoutWideningCreationOrUnobservedVersions(): void
    {
        $legacy = new TableTimes('5.7.44');
        $legacy->intervals = $legacy->creations = ['t1' => ['2026-11-01 00:00:00', '2026-11-01 00:00:00']];
        $modern = new TableTimes('8.4.8');
        $modern->intervals = $legacy->intervals;
        self::assertTrue($legacy->contains('t1', '2026-10-31 23:59:59'));
        self::assertFalse($legacy->contains('t1', '2026-10-31 23:59:58'));
        self::assertFalse($legacy->contains('t1', '2026-11-01 00:00:01'));
        self::assertFalse($legacy->contains('t1', '2026-10-31 23:59:59', true));
        self::assertFalse($modern->contains('t1', '2026-10-31 23:59:59'));
    }

    public function testRowChecksLegacyCreationAndNullUpdateSeparately(): void
    {
        $clock = new TableTimes('5.6.51');
        $clock->creations = ['t1' => ['2026-10-18 00:00:00', '2026-10-18 00:00:01']];
        $row = [0 => 't1', 11 => '2026-10-18 00:00:01', 12 => null];

        self::assertSame([0 => 't1', 11 => '{' . TableTimes::CREATION . '}', 12 => null], $clock->row($row));
        self::assertNull($clock->row([0 => 't1', 11 => '2026-10-18 00:00:02', 12 => null]));
        self::assertNull($clock->row([0 => 't1', 11 => '2026-10-18 00:00:01', 12 => '2026-10-18 00:00:01']));
        self::assertNull($clock->row([0 => 't1', 11 => '2026-10-18 00:00:01']));
    }

    public function testRowCannotExchangeCreationAndInsertionIntervals(): void
    {
        $clock = new TableTimes('5.7.44');
        $clock->creations = ['t1' => ['2026-10-18 00:00:00', '2026-10-18 00:00:01']];
        $clock->intervals = ['t1' => ['2026-10-18 00:00:03', '2026-10-18 00:00:04']];

        self::assertSame([0 => 't1', 11 => '{' . TableTimes::CREATION . '}', 12 => '{' . TableTimes::LEGACY_UPDATE . '}'], $clock->row([0 => 't1', 11 => '2026-10-18 00:00:01', 12 => '2026-10-18 00:00:04']));
        self::assertNull($clock->row([0 => 't1', 11 => '2026-10-18 00:00:04', 12 => '2026-10-18 00:00:01']));
        self::assertNull($clock->row([0 => 't1', 11 => '2026-10-18 00:00:01', 12 => null]));
    }

    /**
     * @return iterable<string, array{array<string, array{string, string}>, array<string, array{string, string}>, array<string, mixed>}>
     * @throws JsonException When the saved observations are malformed
     */
    public static function providerObservedClockLag(): iterable
    {
        /** @var list<array{instance: int, creations: array<string, array{string, string}>, intervals: array<string, array{string, string}>, observation: array{results: list<array{columns: list<list<int|string|list<string>>>, rows: list<list<int|string|null>>}>, warnings: list<array{string, int, string}>}}> $observations */
        $observations = json_decode((string) file_get_contents(__DIR__ . '/fixtures/table-clock-lag.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($observations as $index => $observation) {
            yield 'instance ' . $observation['instance'] . ' sample ' . $index => [$observation['creations'], $observation['intervals'], $observation['observation']];
        }
    }

    /**
     * @param array<string, array{string, string}> $creations
     * @param array<string, array{string, string}> $intervals
     * @param array<string, mixed> $observation
     */
    #[DataProvider('providerObservedClockLag')]
    public function testComparableValidatesSavedIndependentInstanceClockLag(array $creations, array $intervals, array $observation): void
    {
        $clock = new TableTimes('5.7.44');
        $clock->creations = $creations;
        $clock->intervals = $intervals;
        $comparable = $clock->comparable($observation);
        self::assertSame([TableTimes::CREATION, TableTimes::LEGACY_UPDATE], $comparable['contracts'] ?? null);
        self::assertSame($observation['warnings'], $comparable['warnings']);
    }
    /**
     * @return iterable<string, array{string, string, array{string, string}, string}>
     * @throws JsonException When the saved native samples are malformed
     */
    public static function providerObservedModernClockLag(): iterable
    {
        /** @var list<array{release: string, instance: int, iteration: int, table: string, interval: array{string, string}, update: string}> $observations */
        $observations = json_decode((string) file_get_contents(__DIR__ . '/fixtures/table-clock-lag-modern.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($observations as $index => $observation) {
            yield $observation['release'] . ' instance ' . $observation['instance'] . ' sample ' . $index => [$observation['release'], $observation['table'], $observation['interval'], $observation['update']];
        }
    }

    /**
     * @param array{string, string} $interval The server's unmodified before/after insertion samples
     */
    #[DataProvider('providerObservedModernClockLag')]
    public function testContainsValidatesSavedIndependentModernInstanceLag(string $release, string $table, array $interval, string $update): void
    {
        $clock = new TableTimes($release);
        $clock->intervals = $clock->creations = [$table => $interval];
        self::assertTrue($clock->contains($table, $update));
        self::assertFalse($clock->contains($table, $update, true));
        self::assertSame([0 => $table, 12 => '{' . TableTimes::CACHED_UPDATE . '}'], $clock->row([0 => $table, 12 => $update]));
    }

    public function testContainsLimitsModernLagToOneSecondOnTheObservedRelease(): void
    {
        $clock = new TableTimes('8.1.0');
        $clock->intervals = ['t1' => ['2026-11-01 00:00:00', '2026-11-01 00:00:00']];
        self::assertTrue($clock->contains('t1', '2026-10-31 23:59:59'));
        self::assertFalse($clock->contains('t1', '2026-10-31 23:59:58'));
        self::assertFalse($clock->contains('t1', '2026-11-01 00:00:01'));
    }

    public function testUpdateContractKeepsUnknownReleasesStrict(): void
    {
        self::assertSame(TableTimes::LEGACY_UPDATE, (new TableTimes('5.7.44'))->updateContract());
        self::assertSame(TableTimes::CACHED_UPDATE, (new TableTimes('8.4.7'))->updateContract());
        self::assertSame(TableTimes::CONTRACT, (new TableTimes('8.4.8'))->updateContract());
        self::assertSame(TableTimes::CONTRACT, (new TableTimes('5.6.51'))->updateContract());
    }

}
