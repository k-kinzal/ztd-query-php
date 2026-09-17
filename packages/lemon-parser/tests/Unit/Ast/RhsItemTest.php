<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use LemonParser\Ast\Location;
use LemonParser\Ast\RhsItem;
use LemonParser\Ast\Symbol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RhsItem::class)]
#[UsesClass(Location::class)]
#[UsesClass(Symbol::class)]
#[Small]
final class RhsItemTest extends TestCase
{
    public function testIsMultiTerminal(): void
    {
        $single = new RhsItem([new Symbol('expr', new Location(1, 10))], 'A');
        $shared = new RhsItem([new Symbol('PLUS', new Location(1, 15)), new Symbol('MINUS', new Location(1, 20))], null);

        self::assertFalse($single->isMultiTerminal());
        self::assertTrue($shared->isMultiTerminal());
        self::assertSame('A', $single->alias);
    }

    public function testLocation(): void
    {
        $item = new RhsItem([new Symbol('PLUS', new Location(1, 15)), new Symbol('MINUS', new Location(1, 20))], null);

        self::assertSame('1:15', (string) $item->location());
    }
}
