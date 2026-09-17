<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration\Symbols;

use BisonParser\Ast\Declaration\Symbols\Alias;
use BisonParser\Ast\Declaration\Symbols\SymbolClass;
use BisonParser\Ast\Declaration\Symbols\SymbolDeclaration;
use BisonParser\Ast\Declaration\Symbols\SymbolEntry;
use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SymbolDeclaration::class)]
#[UsesClass(Location::class)]
#[UsesClass(Alias::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolClass::class)]
#[UsesClass(SymbolEntry::class)]
#[UsesClass(SymbolKind::class)]
#[Small]
final class SymbolDeclarationTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new SymbolDeclaration(SymbolClass::Token, [new SymbolEntry(new Symbol(SymbolKind::Identifier, 'NUM', new Location(3, 13)), 'int', 258, new Alias('number', false, new Location(3, 21)))], new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame(SymbolClass::Token, $declaration->class);
        self::assertSame('NUM', $declaration->entries[0]->symbol->value);
    }
}
