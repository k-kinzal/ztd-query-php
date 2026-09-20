<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use LemonParser\Ast\Declaration\TokenDeclaration;
use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenDeclaration::class)]
#[UsesClass(Location::class)]
#[UsesClass(Symbol::class)]
#[Small]
final class TokenDeclarationTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new TokenDeclaration([new Symbol('SEMI', new Location(3, 8)), new Symbol('LP', new Location(3, 13))], new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame(['SEMI', 'LP'], array_map(static fn (Symbol $symbol): string => $symbol->name, $declaration->symbols));
    }
}
