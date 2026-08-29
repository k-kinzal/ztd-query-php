<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(Terminal::class)]
final class TerminalTest extends TestCase
{
    public function testValue(): void
    {
        self::assertSame('foo', (new Terminal('foo'))->value());
    }

    public function testValueProperty(): void
    {
        self::assertSame('x', (new Terminal('x'))->value);
    }
}
