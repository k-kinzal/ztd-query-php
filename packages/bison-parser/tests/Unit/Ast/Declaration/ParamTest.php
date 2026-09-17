<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use BisonParser\Ast\Declaration\Param;
use BisonParser\Ast\Declaration\ParamKind;
use BisonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Param::class)]
#[UsesClass(Location::class)]
#[UsesClass(ParamKind::class)]
#[Small]
final class ParamTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new Param(ParamKind::Lex, ['int *n', 'char c'], new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertSame(ParamKind::Lex, $declaration->kind);
        self::assertSame(['int *n', 'char c'], $declaration->codes);
    }
}
