<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Rule;

use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\SymbolItem;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SymbolItem::class)]
#[UsesClass(Location::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolKind::class)]
#[Small]
final class SymbolItemTest extends TestCase
{
    public function testLocation(): void
    {
        $item = new SymbolItem(new Symbol(SymbolKind::CharLiteral, '+', new Location(5, 9)), 'op');

        self::assertSame('5:9', (string) $item->location());
        self::assertSame('+', $item->symbol->value);
        self::assertSame('op', $item->namedReference);
    }
}
