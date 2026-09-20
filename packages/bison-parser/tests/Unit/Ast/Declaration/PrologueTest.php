<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use BisonParser\Ast\Declaration\Prologue;
use BisonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Prologue::class)]
#[UsesClass(Location::class)]
#[Small]
final class PrologueTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new Prologue('#include <stdio.h>', new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame('#include <stdio.h>', $declaration->code);
    }
}
