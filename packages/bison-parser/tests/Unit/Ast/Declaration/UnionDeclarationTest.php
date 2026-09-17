<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use BisonParser\Ast\Declaration\UnionDeclaration;
use BisonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnionDeclaration::class)]
#[UsesClass(Location::class)]
#[Small]
final class UnionDeclarationTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new UnionDeclaration('YYSTYPE', ' int i; ', new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame('YYSTYPE', $declaration->name);
        self::assertSame(' int i; ', $declaration->code);
    }
}
