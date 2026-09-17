<?php

declare(strict_types=1);

namespace Tests\Unit\Printer;

use LemonParser\Ast\CodeBlock;
use LemonParser\Ast\Location;
use LemonParser\Ast\RhsItem;
use LemonParser\Ast\Rule;
use LemonParser\Ast\Symbol;
use LemonParser\Printer\RulePrinter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RulePrinter::class)]
#[UsesClass(CodeBlock::class)]
#[UsesClass(Location::class)]
#[UsesClass(RhsItem::class)]
#[UsesClass(Rule::class)]
#[UsesClass(Symbol::class)]
#[Small]
final class RulePrinterTest extends TestCase
{
    public function testPrint(): void
    {
        $at = new Location(1, 1);
        $items = [new RhsItem([new Symbol('expr', $at)], 'B'), new RhsItem([new Symbol('PLUS', $at), new Symbol('MINUS', $at)], null)];
        $full = new Rule(new Symbol('expr', $at), 'A', $items, new Symbol('STAR', $at), new CodeBlock(' A = B; ', $at), true, $at);
        $bare = new Rule(new Symbol('empty', $at), null, [], null, null, false, $at);

        self::assertSame('expr(A) ::= expr(B) PLUS|MINUS. [STAR] {NEVER-REDUCE} { A = B; }', (new RulePrinter())->print($full));
        self::assertSame('empty ::=.', (new RulePrinter())->print($bare));
    }

    public function testItem(): void
    {
        $at = new Location(1, 1);
        $printer = new RulePrinter();

        self::assertSame('expr(B)', $printer->item(new RhsItem([new Symbol('expr', $at)], 'B')));
        self::assertSame('PLUS|MINUS', $printer->item(new RhsItem([new Symbol('PLUS', $at), new Symbol('MINUS', $at)], null)));
    }
}
