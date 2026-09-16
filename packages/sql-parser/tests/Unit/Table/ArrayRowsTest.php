<?php

declare(strict_types=1);

namespace Tests\Unit\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Table\ArrayRows;

#[CoversClass(ArrayRows::class)]
#[Small]
final class ArrayRowsTest extends TestCase
{
    public function testRow(): void
    {
        $rows = new ArrayRows([[1 => 2], [3 => -1]]);

        self::assertSame([3 => -1], $rows->row(1));
        self::assertSame([], $rows->row(7));
    }

    public function testCount(): void
    {
        self::assertSame(2, (new ArrayRows([[], []]))->count());
    }
}
