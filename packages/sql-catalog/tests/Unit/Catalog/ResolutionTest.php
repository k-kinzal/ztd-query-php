<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(Resolution::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class ResolutionTest extends TestCase
{
    public function testOfAStatementWithoutGapsIsResolved(): void
    {
        self::assertSame(Resolution::Resolved, Resolution::of(TextPattern::fromText('SELECT 1')));
    }

    #[DataProvider('providerOf')]
    public function testOf(Origin $origin, Resolution $expected): void
    {
        $pattern = TextPattern::fromText('SELECT ')
            ->concat(TextPattern::fromHole(new TextHole($origin, TypeShape::unknown())));

        self::assertSame($expected, Resolution::of($pattern));
    }

    /**
     * @return list<array{Origin, Resolution}>
     */
    public static function providerOf(): array
    {
        return [
            [Origin::External, Resolution::ExternalInput],
            [Origin::Budget, Resolution::Incomplete],
            [Origin::Parameter, Resolution::IncompleteModel],
            [Origin::Property, Resolution::IncompleteModel],
            [Origin::Call, Resolution::IncompleteModel],
            [Origin::Loop, Resolution::IncompleteModel],
            [Origin::Branch, Resolution::IncompleteModel],
            [Origin::Unresolved, Resolution::IncompleteModel],
        ];
    }

    public function testOfPrefersTheStoppedSearchOverTheInputItAlsoReached(): void
    {
        $pattern = TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown()))
            ->concat(TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())));

        self::assertSame(Resolution::Incomplete, Resolution::of($pattern));
    }

    public function testIsResolvedOnlyWhenTheTextIsPinnedDown(): void
    {
        self::assertTrue(Resolution::Resolved->isResolved());
        self::assertFalse(Resolution::ExternalInput->isResolved());
    }

    public function testIsClosedSeparatesAFinishedSearchFromAnAbandonedOne(): void
    {
        self::assertTrue(Resolution::Resolved->isClosed());
        self::assertTrue(Resolution::ExternalInput->isClosed());
        self::assertFalse(Resolution::IncompleteModel->isClosed());
        self::assertFalse(Resolution::Incomplete->isClosed());
    }

    public function testDescribeExplainsEveryOutcome(): void
    {
        $descriptions = array_map(
            static fn (Resolution $resolution): string => $resolution->describe(),
            Resolution::cases(),
        );

        self::assertCount(count(Resolution::cases()), array_unique($descriptions));
        self::assertStringContainsString('runtime input', Resolution::ExternalInput->describe());
    }

    /**
     * @return list<array{Resolution, bool}>
     */
    public static function providerWasRead(): array
    {
        return [
            [Resolution::Resolved, true],
            [Resolution::ExternalInput, true],
            [Resolution::IncompleteModel, true],
            [Resolution::Incomplete, false],
            [Resolution::NotAnalyzed, false],
        ];
    }

    #[DataProvider('providerWasRead')]
    public function testWasReadSaysWhetherAStatementWasReadFromTheCallAtAll(Resolution $resolution, bool $expected): void
    {
        self::assertSame($expected, $resolution->wasRead());
    }
}
