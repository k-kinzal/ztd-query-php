<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Production::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(NonTerminal::class)]
final class ProductionTest extends TestCase
{
    public function testConstructor(): void
    {
        $symbols = [new Terminal('A'), new NonTerminal('b')];
        $production = new Production($symbols);

        self::assertSame($symbols, $production->symbols);
    }

    public function testConstructorEmpty(): void
    {
        $production = new Production([]);

        self::assertSame([], $production->symbols);
    }

    public function testSymbolsAreAccessible(): void
    {
        $terminal = new Terminal('X');
        $production = new Production([$terminal]);

        self::assertSame($terminal, $production->symbols[0]);
    }
}
