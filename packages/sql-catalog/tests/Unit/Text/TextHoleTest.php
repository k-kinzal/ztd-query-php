<?php

declare(strict_types=1);

namespace Tests\Unit\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Type\TypeShape;

#[CoversClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class TextHoleTest extends TestCase
{
    public function testDisplayIsTheGapMarker(): void
    {
        self::assertSame('{$}', (new TextHole(Origin::External, TypeShape::unknown()))->display());
    }

    public function testKeepsWhereTheValueCameFrom(): void
    {
        $hole = new TextHole(Origin::Parameter, TypeShape::of(['string']), '$name');
        self::assertSame(Origin::Parameter, $hole->origin);
        self::assertSame('string', $hole->type->display());
        self::assertSame('$name', $hole->expression);
    }

    public function testExpressionIsOptional(): void
    {
        self::assertNull((new TextHole(Origin::Loop, TypeShape::unknown()))->expression);
    }
}
