<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Rule;

use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\MergeItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MergeItem::class)]
#[UsesClass(Location::class)]
#[Small]
final class MergeItemTest extends TestCase
{
    public function testLocation(): void
    {
        $item = new MergeItem('stmtMerge', new Location(5, 9));

        self::assertSame('5:9', (string) $item->location());
        self::assertSame('stmtMerge', $item->tag);
    }
}
