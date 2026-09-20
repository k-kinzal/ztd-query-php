<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration\Symbols;

use BisonParser\Ast\Declaration\Symbols\Alias;
use BisonParser\Ast\Declaration\Symbols\SymbolEntry;
use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SymbolEntry::class)]
#[UsesClass(Alias::class)]
#[UsesClass(Location::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolKind::class)]
#[Small]
final class SymbolEntryTest extends TestCase
{
    public function testAlias(): void
    {
        $entry = new SymbolEntry(new Symbol(SymbolKind::Identifier, 'NUM', new Location(1, 13)), 'int', 258, new Alias('number', false, new Location(1, 21)));

        self::assertSame('NUM', $entry->symbol->value);
        self::assertSame('int', $entry->tag);
        self::assertSame(258, $entry->number);
        self::assertSame('number', $entry->alias?->text);
    }
}
