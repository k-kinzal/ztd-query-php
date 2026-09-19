<?php

declare(strict_types=1);

namespace Tests\Unit\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextSegment;
use SqlCatalog\Type\TypeShape;

#[CoversClass(TextSegment::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class TextSegmentTest extends TestCase
{
    public function testDisplayDistinguishesResolvedTextFromAGap(): void
    {
        $resolved = new LiteralText('WHERE id = ');
        $gap = new TextHole(Origin::External, TypeShape::unknown());
        self::assertSame('WHERE id = ', $resolved->display());
        self::assertSame('{$}', $gap->display());
    }
}
