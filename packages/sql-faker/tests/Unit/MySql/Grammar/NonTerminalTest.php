<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Grammar;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Symbol;

#[CoversNothing]
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
