<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use LemonParser\Ast\Declaration\Associativity;
use LemonParser\Ast\Declaration\PrecedenceDeclaration;
use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrecedenceDeclaration::class)]
#[UsesClass(Location::class)]
#[UsesClass(Associativity::class)]
#[UsesClass(Symbol::class)]
#[Small]
final class PrecedenceDeclarationTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new PrecedenceDeclaration(Associativity::Right, [new Symbol('NOT', new Location(3, 8))], new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame(Associativity::Right, $declaration->associativity);
        self::assertSame('NOT', $declaration->symbols[0]->name);
    }
}
