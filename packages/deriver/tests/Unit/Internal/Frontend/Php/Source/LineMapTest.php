<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Frontend\Php\Source\LineMap
 */
#[CoversClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[Small]
final class LineMapTest extends TestCase
{
    public function testColumnCountsBytesAcrossEmptyAndMultibyteLines(): void
    {
        $map = new \Deriver\Internal\Frontend\Php\Source\LineMap("one\n\n\xc3\xa9x\n");
        self::assertSame(1, $map->column(0));
        self::assertSame(4, $map->column(3));
        self::assertSame(1, $map->column(4));
        self::assertSame(3, $map->column(7));
        self::assertSame(1, $map->column(9));
    }
}
