<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use BisonParser\Ast\Declaration\Declaration;
use BisonParser\Ast\Declaration\Flag;
use BisonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Declaration::class)]
#[UsesClass(Flag::class)]
#[UsesClass(Location::class)]
#[Small]
final class DeclarationTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new Flag('debug', '%debug', new Location(4, 1));

        self::assertSame('4:1', (string) $declaration->location());
    }
}
