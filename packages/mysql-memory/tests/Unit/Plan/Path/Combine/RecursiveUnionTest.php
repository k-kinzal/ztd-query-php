<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Combine;

use MySqlMemory\Plan\Path\Combine\RecursiveUnion;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecursiveUnion::class)]
#[Small]
final class RecursiveUnionTest extends TestCase
{
    public function testWidthIsTheNumberOfColumns(): void
    {
        $working = new WorkingTable(1);

        self::assertSame(1, (new RecursiveUnion(new ZeroRows(4), $working, $working, true, [Domain::integer()], 1000))->width());
    }
}
