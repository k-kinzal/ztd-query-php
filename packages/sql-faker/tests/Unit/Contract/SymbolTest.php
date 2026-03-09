<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\Symbol;

#[CoversClass(Symbol::class)]
final class SymbolTest extends TestCase
{
    public function testConstructsReadonlySymbol(): void
    {
        $symbol = new Symbol('expr', true);

        self::assertSame('expr', $symbol->name);
        self::assertTrue($symbol->isNonTerminal);
    }

    public function testRejectsEmptySymbolName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Symbol name must be non-empty.');

        new Symbol('', false);
    }
}
