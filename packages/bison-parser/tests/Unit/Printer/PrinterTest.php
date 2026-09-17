<?php

declare(strict_types=1);

namespace Tests\Unit\Printer;

use BisonParser\Ast\Declaration\Flag;
use BisonParser\Ast\Declaration\Start;
use BisonParser\Ast\Epilogue;
use BisonParser\Ast\GrammarFile;
use BisonParser\Ast\Line;
use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\Action;
use BisonParser\Ast\Rule\Alternative;
use BisonParser\Ast\Rule\Rule;
use BisonParser\Ast\Rule\SymbolItem;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use BisonParser\Printer\DeclarationPrinter;
use BisonParser\Printer\Printer;
use BisonParser\Printer\RhsPrinter;
use BisonParser\Printer\SymbolPrinter;
use BisonParser\Scanner\Escapes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Printer::class)]
#[UsesClass(Action::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(DeclarationPrinter::class)]
#[UsesClass(Epilogue::class)]
#[UsesClass(Escapes::class)]
#[UsesClass(Flag::class)]
#[UsesClass(GrammarFile::class)]
#[UsesClass(Line::class)]
#[UsesClass(Location::class)]
#[UsesClass(RhsPrinter::class)]
#[UsesClass(Rule::class)]
#[UsesClass(Start::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolItem::class)]
#[UsesClass(SymbolKind::class)]
#[UsesClass(SymbolPrinter::class)]
#[Small]
final class PrinterTest extends TestCase
{
    public function testPrint(): void
    {
        $at = new Location(1, 1);
        $expr = new Symbol(SymbolKind::Identifier, 'expr', $at);
        $rule = new Rule($expr, null, [new Alternative([new SymbolItem($expr, null), new SymbolItem(new Symbol(SymbolKind::CharLiteral, '+', $at), null)], $at), new Alternative([], $at)], $at);
        $file = new GrammarFile([new Flag('debug', '%debug', $at)], [$rule, new Start([$expr], $at)], new Epilogue("\nint main() {}\n", $at));

        self::assertSame("%debug\n%%\nexpr:\n  expr '+'\n| %empty\n;\n%start expr;\n%%\nint main() {}\n", (new Printer())->print($file));
        self::assertSame("%%\n", (new Printer())->print(new GrammarFile([], [], null)));
        self::assertSame("#line 2 \"x.y\"\n%%\n#line 5\n", (new Printer())->print(new GrammarFile([new Line(2, 'x.y', $at)], [new Line(5, null, $at)], null)));
    }

    public function testRule(): void
    {
        $at = new Location(1, 1);
        $rule = new Rule(new Symbol(SymbolKind::Identifier, 'expr', $at), 'e', [new Alternative([new Action(null, ' a ', null, $at)], $at)], $at);

        self::assertSame("expr[e]:\n  { a }\n;", (new Printer())->rule($rule));
    }

    public function testAlternative(): void
    {
        $at = new Location(1, 1);
        $printer = new Printer();

        self::assertSame('%empty', $printer->alternative(new Alternative([], $at)));
        self::assertSame('a { b }', $printer->alternative(new Alternative([new SymbolItem(new Symbol(SymbolKind::Identifier, 'a', $at), null), new Action(null, ' b ', null, $at)], $at)));
    }
}
