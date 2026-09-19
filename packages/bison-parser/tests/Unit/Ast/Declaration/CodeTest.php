<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use BisonParser\Ast\Declaration\Code;
use BisonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Code::class)]
#[UsesClass(Location::class)]
#[Small]
final class CodeTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new Code('requires', ' int x; ', new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame('requires', $declaration->qualifier);
        self::assertSame(' int x; ', $declaration->code);
    }
}
