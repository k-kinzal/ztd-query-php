<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use LemonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Location::class)]
#[Small]
final class LocationTest extends TestCase
{
    public function testToString(): void
    {
        $location = new Location(12, 3);

        self::assertSame(12, $location->line);
        self::assertSame(3, $location->column);
        self::assertSame('12:3', (string) $location);
    }
}
