<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use BisonParser\Ast\Declaration\Option;
use BisonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Option::class)]
#[UsesClass(Location::class)]
#[Small]
final class OptionTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new Option('header', '%defines', null, new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame('header', $declaration->name);
        self::assertSame('%defines', $declaration->raw);
        self::assertNull($declaration->value);
    }
}
