<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use LemonParser\Ast\Declaration\Wildcard;
use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Wildcard::class)]
#[UsesClass(Location::class)]
#[UsesClass(Symbol::class)]
#[Small]
final class WildcardTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new Wildcard(new Symbol('ANY', new Location(3, 11)), new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame('ANY', $declaration->symbol?->name);
        self::assertNull((new Wildcard(null, new Location(4, 1)))->symbol);
    }
}
