<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use BisonParser\Ast\Declaration\Start;
use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Start::class)]
#[UsesClass(Location::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolKind::class)]
#[Small]
final class StartTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new Start([new Symbol(SymbolKind::Identifier, 'program', new Location(3, 8))], new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame('program', $declaration->symbols[0]->value);
    }
}
