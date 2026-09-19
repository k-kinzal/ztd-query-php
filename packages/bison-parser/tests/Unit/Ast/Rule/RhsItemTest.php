<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Rule;

use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\EmptyItem;
use BisonParser\Ast\Rule\RhsItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RhsItem::class)]
#[UsesClass(EmptyItem::class)]
#[UsesClass(Location::class)]
#[Small]
final class RhsItemTest extends TestCase
{
    public function testLocation(): void
    {
        $item = new EmptyItem(new Location(7, 5));

        self::assertSame('7:5', (string) $item->location());
    }
}
