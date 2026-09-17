<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration\Symbols;

use BisonParser\Ast\Declaration\Symbols\Associativity;
use BisonParser\Ast\Declaration\Symbols\PrecedenceDeclaration;
use BisonParser\Ast\Declaration\Symbols\SymbolEntry;
use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrecedenceDeclaration::class)]
#[UsesClass(Location::class)]
#[UsesClass(Associativity::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolEntry::class)]
#[UsesClass(SymbolKind::class)]
#[Small]
final class PrecedenceDeclarationTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new PrecedenceDeclaration(Associativity::Left, [new SymbolEntry(new Symbol(SymbolKind::CharLiteral, '+', new Location(3, 7)), null, null, null)], new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame(Associativity::Left, $declaration->associativity);
        self::assertSame('+', $declaration->entries[0]->symbol->value);
    }
}
