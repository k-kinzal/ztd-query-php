<?php

declare(strict_types=1);

namespace Tests\Unit\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(TextPattern::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TypeShape::class)]
final class TextPatternTest extends TestCase
{
    public function testEmptyHasNoSegments(): void
    {
        self::assertSame([], TextPattern::empty()->segments);
    }

    public function testFromTextKeepsTheCharacters(): void
    {
        self::assertSame('SELECT 1', TextPattern::fromText('SELECT 1')->display());
    }

    public function testFromTextDropsAnEmptyString(): void
    {
        self::assertSame([], TextPattern::fromText('')->segments);
    }

    public function testFromHoleHoldsOneGap(): void
    {
        $pattern = TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown()));
        self::assertCount(1, $pattern->holes());
    }

    public function testFromSegmentsCoalescesAdjacentText(): void
    {
        $pattern = TextPattern::fromSegments([new LiteralText('SELECT '), new LiteralText('1')]);
        self::assertCount(1, $pattern->segments);
        self::assertSame('SELECT 1', $pattern->display());
    }

    public function testFromSegmentsDropsEmptyText(): void
    {
        $pattern = TextPattern::fromSegments([new LiteralText(''), new LiteralText('x')]);
        self::assertCount(1, $pattern->segments);
    }

    public function testConcatJoinsTwoPatterns(): void
    {
        $joined = TextPattern::fromText('SELECT ')->concat(TextPattern::fromText('1'));
        self::assertSame('SELECT 1', $joined->text());
    }

    public function testIsExactOnlyWithoutGaps(): void
    {
        self::assertTrue(TextPattern::fromText('SELECT 1')->isExact());
        self::assertFalse(
            TextPattern::fromHole(new TextHole(Origin::Loop, TypeShape::unknown()))->isExact(),
        );
    }

    public function testTextIsNullWhenAGapRemains(): void
    {
        $pattern = TextPattern::fromText('id = ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown())));
        self::assertNull($pattern->text());
    }

    public function testHolesAreReturnedInSourceOrder(): void
    {
        $first = new TextHole(Origin::External, TypeShape::unknown());
        $second = new TextHole(Origin::Loop, TypeShape::unknown());
        $pattern = TextPattern::fromSegments([$first, new LiteralText(' and '), $second]);
        self::assertSame([$first, $second], $pattern->holes());
    }

    public function testDisplayShowsGapsAsAMarker(): void
    {
        $pattern = TextPattern::fromText('id = ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown())));
        self::assertSame('id = {$}', $pattern->display());
    }

    public function testRenderSubstitutesTheGivenStandIn(): void
    {
        $pattern = TextPattern::fromText('id = ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown())));
        self::assertSame('id = gap', $pattern->render('gap'));
    }

    public function testSignatureKeepsGapOriginsApart(): void
    {
        $left = TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown()));
        $right = TextPattern::fromHole(new TextHole(Origin::Loop, TypeShape::unknown()));
        self::assertNotSame($left->signature(), $right->signature());
    }

    public function testEqualsComparesShapesRatherThanObjects(): void
    {
        self::assertTrue(TextPattern::fromText('a')->equals(TextPattern::fromText('a')));
        self::assertFalse(TextPattern::fromText('a')->equals(TextPattern::fromText('b')));
    }

    public function testIsEmptyOnlyWithoutAnySegment(): void
    {
        self::assertTrue(TextPattern::empty()->isEmpty());
        self::assertFalse(TextPattern::fromText('x')->isEmpty());
    }
}
