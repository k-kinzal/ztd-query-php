<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration\Symbols;

use BisonParser\Ast\Declaration\Symbols\SymbolClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SymbolClass::class)]
#[Small]
final class SymbolClassTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame(['token', 'nterm', 'type'], array_map(static fn (SymbolClass $case): string => $case->value, SymbolClass::cases()));
    }
}
