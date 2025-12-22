<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Symbol;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NonTerminal::class)]
final class NonTerminalTest extends TestCase
{
    public function testValue(): void
    {
        self::assertSame('foo', (new NonTerminal('foo'))->value());
    }

    public function testImplementsSymbolInterface(): void
    {
        self::assertInstanceOf(Symbol::class, new NonTerminal('x'));
    }

    public function testValueProperty(): void
    {
        self::assertSame('x', (new NonTerminal('x'))->value);
    }
}
