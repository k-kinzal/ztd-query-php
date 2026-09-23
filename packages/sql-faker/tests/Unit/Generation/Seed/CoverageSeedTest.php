<?php

declare(strict_types=1);

namespace Tests\Unit\Generation\Seed;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Seed\CoverageSeed;

#[CoversClass(CoverageSeed::class)]
final class CoverageSeedTest extends TestCase
{
    public function testNameUsesTheTargetOrElseTheInputHash(): void
    {
        self::assertSame('select_stmt-3', (new CoverageSeed("\x01", 'select_stmt', 3, 7, 'SELECT 1', [], []))->name());
        self::assertSame(hash('sha256', "\x01"), (new CoverageSeed("\x01", null, null, 7, 'SELECT 1', [], []))->name());
    }
}
