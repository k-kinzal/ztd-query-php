<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\Severity;

#[CoversClass(Severity::class)]
final class SeverityTest extends TestCase
{
    public function testRankOrdersTheSeverities(): void
    {
        self::assertSame(3, Severity::High->rank());
        self::assertSame(2, Severity::Medium->rank());
        self::assertSame(1, Severity::Low->rank());
        self::assertSame(0, Severity::Info->rank());
    }

    public function testAtLeastComparesByRank(): void
    {
        self::assertTrue(Severity::High->atLeast(Severity::Medium));
        self::assertTrue(Severity::Medium->atLeast(Severity::Medium));
        self::assertFalse(Severity::Low->atLeast(Severity::Medium));
    }
}
