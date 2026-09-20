<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use BisonParser\Ast\Declaration\Expect;
use BisonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Expect::class)]
#[UsesClass(Location::class)]
#[Small]
final class ExpectTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new Expect(2, true, new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame(2, $declaration->count);
        self::assertTrue($declaration->reduceReduce);
    }
}
