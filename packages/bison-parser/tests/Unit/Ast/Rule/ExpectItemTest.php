<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Rule;

use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\ExpectItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpectItem::class)]
#[UsesClass(Location::class)]
#[Small]
final class ExpectItemTest extends TestCase
{
    public function testLocation(): void
    {
        $item = new ExpectItem(1, false, new Location(5, 9));

        self::assertSame('5:9', (string) $item->location());
        self::assertSame(1, $item->count);
        self::assertFalse($item->reduceReduce);
    }
}
