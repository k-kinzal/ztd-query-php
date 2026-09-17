<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use LemonParser\Ast\Declaration\Fallback;
use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fallback::class)]
#[UsesClass(Location::class)]
#[UsesClass(Symbol::class)]
#[Small]
final class FallbackTest extends TestCase
{
    public function testFallback(): void
    {
        $declaration = new Fallback([new Symbol('ID', new Location(3, 11)), new Symbol('ABORT', new Location(3, 14))], new Location(3, 1));

        self::assertSame('ID', $declaration->fallback()?->name);
        self::assertNull((new Fallback([], new Location(4, 1)))->fallback());
    }

    public function testTokens(): void
    {
        $declaration = new Fallback([new Symbol('ID', new Location(3, 11)), new Symbol('ABORT', new Location(3, 14)), new Symbol('AFTER', new Location(3, 20))], new Location(3, 1));

        self::assertSame(['ABORT', 'AFTER'], array_map(static fn (Symbol $symbol): string => $symbol->name, $declaration->tokens()));
        self::assertSame([], (new Fallback([], new Location(4, 1)))->tokens());
    }

    public function testLocation(): void
    {
        self::assertSame('3:1', (string) (new Fallback([], new Location(3, 1)))->location());
    }
}
