<?php

declare(strict_types=1);

namespace Tests\Unit\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Table\PackedRows;
use SqlParser\Table\TableCodec;

#[CoversClass(PackedRows::class)]
#[UsesClass(TableCodec::class)]
#[Small]
final class PackedRowsTest extends TestCase
{
    public function testRow(): void
    {
        $codec = new TableCodec();
        $first = $codec->encodeRow([5 => 3, 1 => -2]);
        $second = $codec->encodeRow([2 => -2147483648]);
        $rows = new PackedRows($first . $second, [0, strlen($first), strlen($first . $second)]);

        self::assertSame([1 => -2, 5 => 3], $rows->row(0));
        self::assertSame([2 => -2147483648], $rows->row(1));
        self::assertSame([1 => -2, 5 => 3], $rows->row(0));
    }

    public function testRowIsEmptyForAStateWithoutActionsOrOutOfRange(): void
    {
        $rows = new PackedRows('', [0, 0]);

        self::assertSame([], $rows->row(0));
        self::assertSame([], $rows->row(3));
    }

    public function testCount(): void
    {
        self::assertSame(2, (new PackedRows('', [0, 0, 0]))->count());
        self::assertSame(0, (new PackedRows('', []))->count());
    }
}
