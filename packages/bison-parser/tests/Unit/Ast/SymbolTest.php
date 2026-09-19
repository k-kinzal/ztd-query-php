<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Symbol::class)]
#[UsesClass(Location::class)]
#[UsesClass(SymbolKind::class)]
#[Small]
final class SymbolTest extends TestCase
{
    public function testIsIdentifier(): void
    {
        $identifier = new Symbol(SymbolKind::Identifier, 'expr', new Location(1, 1));
        $literal = new Symbol(SymbolKind::CharLiteral, '+', new Location(1, 6));
        $string = new Symbol(SymbolKind::String, 'number', new Location(1, 10));

        self::assertTrue($identifier->isIdentifier());
        self::assertFalse($literal->isIdentifier());
        self::assertFalse($string->isIdentifier());
        self::assertSame('1:6', (string) $literal->location);
        self::assertNull($identifier->spelling);
        self::assertSame("'\\101'", (new Symbol(SymbolKind::CharLiteral, 'A', new Location(1, 1), "'\\101'"))->spelling);
    }
}
