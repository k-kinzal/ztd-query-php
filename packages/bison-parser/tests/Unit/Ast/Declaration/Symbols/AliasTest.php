<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration\Symbols;

use BisonParser\Ast\Declaration\Symbols\Alias;
use BisonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Alias::class)]
#[UsesClass(Location::class)]
#[Small]
final class AliasTest extends TestCase
{
    public function testTranslatable(): void
    {
        $alias = new Alias('number', true, new Location(2, 14));

        self::assertSame('number', $alias->text);
        self::assertTrue($alias->translatable);
        self::assertSame('2:14', (string) $alias->location);
        self::assertNull($alias->spelling);
        self::assertSame('_("number")', (new Alias('number', true, new Location(2, 14), '_("number")'))->spelling);
    }
}
