<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Conformance\ConformanceReport;
use SqlCatalog\Conformance\ObservedStatement;

#[CoversClass(ConformanceReport::class)]
#[UsesClass(ObservedStatement::class)]
final class ConformanceReportTest extends TestCase
{
    public function testIsSoundOnlyWhenNothingWasMissedOrRejected(): void
    {
        self::assertTrue((new ConformanceReport(2, 2, 2, [], []))->isSound());
        self::assertFalse((new ConformanceReport(2, 1, 1, [new ObservedStatement('SELECT 1')], []))->isSound());
        self::assertFalse((new ConformanceReport(2, 2, 2, [], ['a mismatch']))->isSound());
    }

    public function testCoverageIsTheShareOfStatementsTheCatalogMatches(): void
    {
        self::assertSame(0.5, (new ConformanceReport(4, 2, 1, [], []))->coverage());
        self::assertSame(1.0, (new ConformanceReport(0, 0, 0, [], []))->coverage());
    }

    public function testPrecisionIsTheShareMatchedWithoutGaps(): void
    {
        self::assertSame(0.25, (new ConformanceReport(4, 2, 1, [], []))->precision());
        self::assertSame(1.0, (new ConformanceReport(0, 0, 0, [], []))->precision());
    }

    public function testDisplayWritesTheCounts(): void
    {
        self::assertSame(
            '4 observed, 2 covered (50.0%), 1 fully resolved (25.0%), 0 value mismatch(es)',
            (new ConformanceReport(4, 2, 1, [], []))->display(),
        );
    }
}
