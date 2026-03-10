<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Symbol;
use SqlFaker\Grammar\Terminal;

#[CoversNothing]
final class TerminalTest extends TestCase
{
    public function testValue(): void
    {
        self::assertSame('foo', (new Terminal('foo'))->value());
    }

    public function testImplementsSymbolInterface(): void
    {
        self::assertInstanceOf(Symbol::class, new Terminal('x'));
    }

    public function testValueProperty(): void
    {
        self::assertSame('x', (new Terminal('x'))->value);
    }
}
