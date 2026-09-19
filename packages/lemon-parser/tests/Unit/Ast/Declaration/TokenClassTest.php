<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use LemonParser\Ast\Declaration\TokenClass;
use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenClass::class)]
#[UsesClass(Location::class)]
#[UsesClass(Symbol::class)]
#[Small]
final class TokenClassTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new TokenClass(new Symbol('id', new Location(3, 14)), [new Symbol('ID', new Location(3, 17)), new Symbol('INDEXED', new Location(3, 20))], new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame('id', $declaration->name->name);
        self::assertSame(['ID', 'INDEXED'], array_map(static fn (Symbol $symbol): string => $symbol->name, $declaration->tokens));
    }
}
