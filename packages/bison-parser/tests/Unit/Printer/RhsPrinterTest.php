<?php

declare(strict_types=1);

namespace Tests\Unit\Printer;

use BisonParser\Ast\Line;
use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\Action;
use BisonParser\Ast\Rule\DprecItem;
use BisonParser\Ast\Rule\EmptyItem;
use BisonParser\Ast\Rule\ExpectItem;
use BisonParser\Ast\Rule\MergeItem;
use BisonParser\Ast\Rule\PrecItem;
use BisonParser\Ast\Rule\Predicate;
use BisonParser\Ast\Rule\SymbolItem;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use BisonParser\Printer\DeclarationPrinter;
use BisonParser\Printer\RhsPrinter;
use BisonParser\Printer\SymbolPrinter;
use BisonParser\Scanner\Escapes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RhsPrinter::class)]
#[UsesClass(Action::class)]
#[UsesClass(DprecItem::class)]
#[UsesClass(EmptyItem::class)]
#[UsesClass(Escapes::class)]
#[UsesClass(ExpectItem::class)]
#[UsesClass(Line::class)]
#[UsesClass(DeclarationPrinter::class)]
#[UsesClass(Location::class)]
#[UsesClass(MergeItem::class)]
#[UsesClass(PrecItem::class)]
#[UsesClass(Predicate::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolItem::class)]
#[UsesClass(SymbolKind::class)]
#[UsesClass(SymbolPrinter::class)]
#[Small]
final class RhsPrinterTest extends TestCase
{
    public function testPrint(): void
    {
        $printer = new RhsPrinter();
        $at = new Location(1, 1);

        self::assertSame('expr[e]', $printer->print(new SymbolItem(new Symbol(SymbolKind::Identifier, 'expr', $at), 'e')));
        self::assertSame("'+'", $printer->print(new SymbolItem(new Symbol(SymbolKind::CharLiteral, '+', $at), null)));
        self::assertSame('{ a }', $printer->print(new Action(null, ' a ', null, $at)));
        self::assertSame('<int>{ b }[r]', $printer->print(new Action('int', ' b ', 'r', $at)));
        self::assertSame('%?{ p }', $printer->print(new Predicate(' p ', $at)));
        self::assertSame('%empty', $printer->print(new EmptyItem($at)));
        self::assertSame('%prec UMINUS', $printer->print(new PrecItem(new Symbol(SymbolKind::Identifier, 'UMINUS', $at), $at)));
        self::assertSame('%dprec 2', $printer->print(new DprecItem(2, $at)));
        self::assertSame('%merge <m>', $printer->print(new MergeItem('m', $at)));
        self::assertSame('%expect 1', $printer->print(new ExpectItem(1, false, $at)));
        self::assertSame('%expect-rr 3', $printer->print(new ExpectItem(3, true, $at)));
        self::assertSame("\n#line 4 \"x.y\"\n", $printer->print(new Line(4, 'x.y', $at)));
    }

    public function testReference(): void
    {
        $printer = new RhsPrinter();

        self::assertSame('', $printer->reference(null));
        self::assertSame('[name]', $printer->reference('name'));
    }
}
