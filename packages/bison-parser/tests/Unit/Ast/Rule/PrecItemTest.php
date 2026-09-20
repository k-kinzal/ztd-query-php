<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Rule;

use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\PrecItem;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrecItem::class)]
#[UsesClass(Location::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolKind::class)]
#[Small]
final class PrecItemTest extends TestCase
{
    public function testLocation(): void
    {
        $item = new PrecItem(new Symbol(SymbolKind::Identifier, 'UMINUS', new Location(5, 15)), new Location(5, 9));

        self::assertSame('5:9', (string) $item->location());
        self::assertSame('UMINUS', $item->symbol->value);
    }
}
