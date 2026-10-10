<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\TableTimes;
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
        yield 'legacy creation clock' => ['SHOW TABLE STATUS', '5.7.44', false];
        yield 'other database' => ['SHOW TABLE STATUS FROM sys', '8.4.7', false];
        yield 'arbitrary predicate' => ['SHOW TABLE STATUS WHERE some_function()', '8.4.7', false];
        yield 'preceding write' => ['INSERT INTO t1 VALUES(9); SHOW TABLE STATUS', '8.4.7', false];
        yield 'following statement' => ['SHOW TABLE STATUS; SELECT 1', '8.4.7', false];
        yield 'scalar expression' => ['SELECT UPDATE_TIME FROM information_schema.TABLES', '8.4.7', false];
    }

    #[DataProvider('providerStatements')]
    public function testHandlesOnlyThePlainModernInspection(string $sql, string $version, bool $handled): void
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
        yield 'before insertion' => ['2026-10-10 00:59:59', false];
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
        self::assertSame($valid ? [TableTimes::CONTRACT] : null, $comparable['contracts'] ?? null);
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
}
