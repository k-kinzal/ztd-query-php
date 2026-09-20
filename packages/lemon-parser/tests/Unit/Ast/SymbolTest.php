<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Symbol::class)]
#[UsesClass(Location::class)]
#[Small]
final class SymbolTest extends TestCase
{
    public function testIsTerminal(): void
    {
        self::assertTrue((new Symbol('PLUS', new Location(1, 1)))->isTerminal());
        self::assertTrue((new Symbol('Ab', new Location(1, 1)))->isTerminal());
        self::assertFalse((new Symbol('expr', new Location(1, 1)))->isTerminal());
        self::assertFalse((new Symbol('aB', new Location(1, 1)))->isTerminal());
        self::assertFalse((new Symbol('', new Location(1, 1)))->isTerminal());
    }

    public function testIsNonterminal(): void
    {
        $symbol = new Symbol('expr', new Location(2, 5));

        self::assertTrue($symbol->isNonterminal());
        self::assertTrue((new Symbol('aB', new Location(1, 1)))->isNonterminal());
        self::assertFalse((new Symbol('PLUS', new Location(1, 1)))->isNonterminal());
        self::assertFalse((new Symbol('Ab', new Location(1, 1)))->isNonterminal());
        self::assertSame('2:5', (string) $symbol->location);
    }
}
