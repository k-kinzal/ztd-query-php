<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Rule;

use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\Action;
use BisonParser\Ast\Rule\Alternative;
use BisonParser\Ast\Rule\PrecItem;
use BisonParser\Ast\Rule\SymbolItem;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Alternative::class)]
#[UsesClass(Action::class)]
#[UsesClass(Location::class)]
#[UsesClass(PrecItem::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolItem::class)]
#[UsesClass(SymbolKind::class)]
#[Small]
final class AlternativeTest extends TestCase
{
    public function testSymbols(): void
    {
        $left = new Symbol(SymbolKind::Identifier, 'expr', new Location(2, 3));
        $plus = new Symbol(SymbolKind::CharLiteral, '+', new Location(2, 8));
        $alternative = new Alternative([
            new SymbolItem($left, null),
            new SymbolItem($plus, null),
            new Action(null, ' $$ = $1; ', null, new Location(2, 12)),
            new PrecItem(new Symbol(SymbolKind::Identifier, 'UMINUS', new Location(2, 30)), new Location(2, 24)),
        ], new Location(2, 3));

        self::assertSame([$left, $plus], $alternative->symbols());
        self::assertSame('2:3', (string) $alternative->location);
    }
}
