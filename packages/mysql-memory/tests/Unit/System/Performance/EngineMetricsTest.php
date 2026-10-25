<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\System\Performance\EngineMetrics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(EngineMetrics::class)]
#[Small]
final class EngineMetricsTest extends TestCase
{
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
