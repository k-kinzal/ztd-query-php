<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Rule;

use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\EmptyItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmptyItem::class)]
#[UsesClass(Location::class)]
#[Small]
final class EmptyItemTest extends TestCase
{
    public function testLocation(): void
    {
        $item = new EmptyItem(new Location(5, 9));

        self::assertSame('5:9', (string) $item->location());
    }
}
