<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use BisonParser\Ast\Location;
use BisonParser\Ast\Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tag::class)]
#[UsesClass(Location::class)]
#[Small]
final class TagTest extends TestCase
{
    public function testIsAny(): void
    {
        self::assertTrue((new Tag(Tag::ANY, new Location(1, 1)))->isAny());
        self::assertFalse((new Tag('int', new Location(1, 1)))->isAny());
        self::assertFalse((new Tag(Tag::NONE, new Location(1, 1)))->isAny());
    }

    public function testIsNone(): void
    {
        self::assertTrue((new Tag(Tag::NONE, new Location(1, 1)))->isNone());
        self::assertFalse((new Tag('int', new Location(1, 1)))->isNone());
        self::assertFalse((new Tag(Tag::ANY, new Location(1, 1)))->isNone());
    }
}
