<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use BisonParser\Ast\Declaration\Flag;
use BisonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Flag::class)]
#[UsesClass(Location::class)]
#[Small]
final class FlagTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new Flag('pure-parser', '%pure_parser', new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame('pure-parser', $declaration->name);
        self::assertSame('%pure_parser', $declaration->raw);
    }
}
