<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use Deriver\Source\LineMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\LineMap
 */
#[CoversClass(LineMap::class)]
#[Small]
final class LineMapTest extends TestCase
{
    public function testColumnCountsBytesAcrossEmptyAndMultibyteLines(): void
    {
        $map = new LineMap("one\n\n\xc3\xa9x\n");
        self::assertSame(1, $map->column(0));
        self::assertSame(4, $map->column(3));
        self::assertSame(1, $map->column(4));
        self::assertSame(3, $map->column(7));
        self::assertSame(1, $map->column(9));
    }
}
