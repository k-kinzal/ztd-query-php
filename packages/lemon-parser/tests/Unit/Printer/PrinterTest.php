<?php

declare(strict_types=1);

namespace Tests\Unit\Printer;

use LemonParser\Ast\Declaration\Associativity;
use LemonParser\Ast\Declaration\PrecedenceDeclaration;
use LemonParser\Ast\GrammarFile;
use LemonParser\Ast\Location;
use LemonParser\Ast\RhsItem;
use LemonParser\Ast\Rule;
use LemonParser\Ast\Symbol;
use LemonParser\Printer\DeclarationPrinter;
use LemonParser\Printer\Printer;
use LemonParser\Printer\RulePrinter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Printer::class)]
#[UsesClass(Associativity::class)]
#[UsesClass(DeclarationPrinter::class)]
#[UsesClass(GrammarFile::class)]
#[UsesClass(Location::class)]
#[UsesClass(PrecedenceDeclaration::class)]
#[UsesClass(RhsItem::class)]
#[UsesClass(Rule::class)]
#[UsesClass(RulePrinter::class)]
#[UsesClass(Symbol::class)]
#[Small]
final class PrinterTest extends TestCase
{
    public function testPrint(): void
    {
        $at = new Location(1, 1);
        $rule = new Rule(new Symbol('expr', $at), null, [new RhsItem([new Symbol('NUM', $at)], null)], null, null, false, $at);
        $file = new GrammarFile([new PrecedenceDeclaration(Associativity::Left, [new Symbol('PLUS', $at)], $at), $rule]);

        self::assertSame("%left PLUS.\nexpr ::= NUM.\n", (new Printer())->print($file));
        self::assertSame('', (new Printer())->print(new GrammarFile([])));
    }
}
