<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use BisonParser\Ast\SymbolKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SymbolKind::class)]
#[Small]
final class SymbolKindTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame(['identifier', 'char', 'string'], array_map(static fn (SymbolKind $case): string => $case->value, SymbolKind::cases()));
    }
}
