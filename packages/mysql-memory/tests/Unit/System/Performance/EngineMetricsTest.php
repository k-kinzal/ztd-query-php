<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\System\Performance\EngineMetrics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(EngineMetrics::class)]
#[Small]
final class EngineMetricsTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, int, string, string}>
     */
    public static function providerOrderedReleases(): iterable
    {
        yield '5.6' => [GrammarRelease::MySql5651, 167, 'row_size', 'row_count'];
        yield '5.7' => [GrammarRelease::MySql5744, 229, 'size', 'count'];
        yield '8.0' => [GrammarRelease::MySql8044, 248, 'size', 'count'];
        yield '8.1' => [GrammarRelease::MySql810, 248, 'size', 'count'];
        yield '8.2' => [GrammarRelease::MySql820, 254, 'size', 'count'];
        yield '8.3' => [GrammarRelease::MySql830, 254, 'size', 'count'];
        yield '8.4' => [GrammarRelease::MySql847, 254, 'size', 'count'];
        yield '9.0' => [GrammarRelease::MySql901, 254, 'size', 'count'];
        yield '9.1' => [GrammarRelease::MySql910, 257, 'size', 'count'];
    }

    #[DataProvider('providerOrderedReleases')]
    public function testRowsKeepBufferDimensionsWithTheirMemoryAndTheEngineTotalLast(GrammarRelease $release, int $count, string $size, string $entries): void
    {
        $rows = (new EngineMetrics())->rows($release);
        self::assertCount($count, $rows);
        self::assertSame([
            'events_waits_current.' . $size,
            'events_waits_current.' . $entries,
            'events_waits_history.' . $size,
            'events_waits_history.' . $entries,
            'events_waits_history.memory',
            'events_waits_history_long.' . $size,
            'events_waits_history_long.' . $entries,
            'events_waits_history_long.memory',
        ], array_column(array_slice($rows, 0, 8), 1));
        self::assertSame(['performance_schema', 'performance_schema.memory', '0'], $rows[$count - 1]);
    }

    public function testRowsReportZeroNativeAllocations(): void
    {
        $rows = (new EngineMetrics())->rows(GrammarRelease::MySql847);
        self::assertContains(['performance_schema', 'performance_schema.memory', '0'], $rows);
        self::assertSame(['0'], array_values(array_unique(array_column($rows, 2))));
        self::assertCount(count($rows), array_unique(array_column($rows, 1)));
    }

    public function testRowsRetainTheMetricNamesOfTheSelectedRelease(): void
    {
        $metrics = new EngineMetrics();
        self::assertContains(['performance_schema', 'events_waits_current.row_size', '0'], $metrics->rows(GrammarRelease::MySql5651));
        self::assertNotContains(['performance_schema', 'events_waits_current.row_size', '0'], $metrics->rows(GrammarRelease::MySql847));
        self::assertContains(['performance_schema', '(pfs_logger_class).count', '0'], $metrics->rows(GrammarRelease::MySql910));
        self::assertNotContains(['performance_schema', '(pfs_logger_class).count', '0'], $metrics->rows(GrammarRelease::MySql847));
    }
}
