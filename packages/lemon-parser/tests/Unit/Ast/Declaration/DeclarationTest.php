<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use LemonParser\Ast\Declaration\Declaration;
use LemonParser\Ast\Declaration\TokenDeclaration;
use LemonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Declaration::class)]
#[UsesClass(Location::class)]
#[UsesClass(TokenDeclaration::class)]
#[Small]
final class DeclarationTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new TokenDeclaration([], new Location(4, 1));

        self::assertSame('4:1', (string) $declaration->location());
    }
}
