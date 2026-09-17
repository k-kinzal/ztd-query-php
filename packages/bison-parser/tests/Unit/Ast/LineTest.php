<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use BisonParser\Ast\Line;
use BisonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Line::class)]
#[UsesClass(Location::class)]
#[Small]
final class LineTest extends TestCase
{
    public function testLocation(): void
    {
        $line = new Line(10, 'other.y', new Location(3, 1));

        self::assertSame(10, $line->line);
        self::assertSame('other.y', $line->file);
        self::assertSame('3:1', (string) $line->location());
        self::assertNull((new Line(1, null, new Location(1, 1)))->file);
    }
}
