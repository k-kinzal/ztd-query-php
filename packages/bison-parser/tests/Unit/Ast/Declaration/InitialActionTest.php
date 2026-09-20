<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use BisonParser\Ast\Declaration\InitialAction;
use BisonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InitialAction::class)]
#[UsesClass(Location::class)]
#[Small]
final class InitialActionTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new InitialAction(' @$ = 1; ', new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame(' @$ = 1; ', $declaration->code);
    }
}
