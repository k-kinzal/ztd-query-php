<?php

declare(strict_types=1);

namespace Tests\Unit\Printer;

use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use BisonParser\Ast\Tag;
use BisonParser\Printer\SymbolPrinter;
use BisonParser\Scanner\Escapes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SymbolPrinter::class)]
#[UsesClass(Escapes::class)]
#[UsesClass(Location::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolKind::class)]
#[UsesClass(Tag::class)]
#[Small]
final class SymbolPrinterTest extends TestCase
{
    public function testSymbol(): void
    {
        $printer = new SymbolPrinter();

        self::assertSame('expr', $printer->symbol(new Symbol(SymbolKind::Identifier, 'expr', new Location(1, 1))));
        self::assertSame("'\\n'", $printer->symbol(new Symbol(SymbolKind::CharLiteral, "\n", new Location(1, 1))));
        self::assertSame("'\\''", $printer->symbol(new Symbol(SymbolKind::CharLiteral, "'", new Location(1, 1))));
        self::assertSame('"a\\"b"', $printer->symbol(new Symbol(SymbolKind::String, 'a"b', new Location(1, 1))));
        self::assertSame("'\\101'", $printer->symbol(new Symbol(SymbolKind::CharLiteral, 'A', new Location(1, 1), "'\\101'")));
        self::assertSame('"\\x61"', $printer->symbol(new Symbol(SymbolKind::String, 'a', new Location(1, 1), '"\\x61"')));
    }

    public function testString(): void
    {
        self::assertSame('"tab\\there"', (new SymbolPrinter())->string("tab\there"));
    }

    public function testTag(): void
    {
        $printer = new SymbolPrinter();

        self::assertSame('<int>', $printer->tag(new Tag('int', new Location(1, 1))));
        self::assertSame('<*>', $printer->tag(new Tag(Tag::ANY, new Location(1, 1))));
        self::assertSame('<>', $printer->tag(new Tag(Tag::NONE, new Location(1, 1))));
    }

    public function testCode(): void
    {
        self::assertSame('{ $$ = $1; }', (new SymbolPrinter())->code(' $$ = $1; '));
    }
}
