<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use BisonParser\Ast\Declaration\CodeProps;
use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use BisonParser\Ast\Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CodeProps::class)]
#[UsesClass(Location::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolKind::class)]
#[UsesClass(Tag::class)]
#[Small]
final class CodePropsTest extends TestCase
{
    public function testLocation(): void
    {
        $declaration = new CodeProps(true, ' fprintf(yyo, "%d", $$); ', [new Symbol(SymbolKind::Identifier, 'NUM', new Location(3, 30)), new Tag(Tag::ANY, new Location(3, 34))], new Location(3, 1));

        self::assertSame('3:1', (string) $declaration->location());
        self::assertTrue($declaration->printer);
        self::assertSame(' fprintf(yyo, "%d", $$); ', $declaration->code);
        self::assertCount(2, $declaration->targets);
    }
}
