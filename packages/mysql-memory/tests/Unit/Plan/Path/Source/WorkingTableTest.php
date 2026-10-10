<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Source;

use MySqlMemory\Plan\Path\Source\WorkingTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkingTable::class)]
#[Small]
final class WorkingTableTest extends TestCase
{
    public function testWidthIsTheNumberOfColumns(): void
    {
        $working = new WorkingTable(2);

        self::assertSame(2, $working->width());
        self::assertSame([], $working->rows);
    }
}
