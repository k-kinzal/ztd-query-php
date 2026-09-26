<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextGeneralization;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

#[CoversClass(TextGeneralization::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class TextGeneralizationTest extends TestCase
{
    public function testMergeKeepsWhatTheTwoSidesAgreeOn(): void
    {
        $merged = (new TextGeneralization(Origin::Loop))->merge(
            TextPattern::fromText('SELECT a FROM t'),
            TextPattern::fromText('SELECT b FROM t'),
        );
        self::assertSame('SELECT {$} FROM t', $merged->display());
    }

    public function testMergeReturnsTheSameShapeWhenBothSidesAgree(): void
    {
        $merged = (new TextGeneralization(Origin::Loop))->merge(
            TextPattern::fromText('SELECT 1'),
            TextPattern::fromText('SELECT 1'),
        );
        self::assertSame('SELECT 1', $merged->display());
    }

    public function testMergeCoversAGrowingSuffix(): void
    {
        $merged = (new TextGeneralization(Origin::Loop))->merge(
            TextPattern::fromText('WHERE 1 AND x'),
            TextPattern::fromText('WHERE 1 AND x AND x'),
        );
        self::assertSame('WHERE 1 AND x{$}', $merged->display());
    }

    public function testMergeResolvedAnswersOnlyForTwoResolvedStrings(): void
    {
        $generalization = new TextGeneralization(Origin::Branch);
        $exact = TextPattern::fromText('SELECT a FROM t');
        $gapped = TextPattern::fromHole(new TextHole(Origin::Loop, TypeShape::unknown()));

        self::assertSame(
            'SELECT {$} FROM t',
            $generalization->mergeResolved($exact, TextPattern::fromText('SELECT b FROM t'))?->display(),
        );
        self::assertNull($generalization->mergeResolved($exact, $gapped));
    }

    public function testSharedPrefixLengthCountsTheLeadingBytesTwoStringsShare(): void
    {
        $generalization = new TextGeneralization(Origin::Branch);

        self::assertSame(3, $generalization->sharedPrefixLength('abcd', 'abcx'));
        self::assertSame(0, $generalization->sharedPrefixLength('a', 'b'));
        self::assertSame(2, $generalization->sharedPrefixLength('ab', 'abcd'));
    }

    public function testMergeAllFoldsEveryAlternative(): void
    {
        $merged = (new TextGeneralization(Origin::Branch))->mergeAll([
            TextPattern::fromText('ORDER BY a'),
            TextPattern::fromText('ORDER BY b'),
            TextPattern::fromText('ORDER BY c'),
        ]);
        self::assertSame('ORDER BY {$}', $merged->display());
    }

    public function testMergeAllOfNothingIsEmpty(): void
    {
        self::assertTrue((new TextGeneralization(Origin::Branch))->mergeAll([])->isEmpty());
    }

    public function testAtomsSplitTextIntoCharactersAndKeepGapsWhole(): void
    {
        $hole = new TextHole(Origin::Loop, TypeShape::unknown());
        $atoms = (new TextGeneralization(Origin::Loop))->atoms(
            TextPattern::fromSegments([new LiteralText('ab'), $hole]),
        );
        self::assertSame(['a', 'b', $hole], $atoms);
    }

    public function testCommonPrefixLengthCountsSharedLeadingAtoms(): void
    {
        $generalization = new TextGeneralization(Origin::Loop);
        self::assertSame(2, $generalization->commonPrefixLength(['a', 'b', 'c'], ['a', 'b', 'x']));
        self::assertSame(0, $generalization->commonPrefixLength(['a'], ['b']));
    }

    public function testCommonSuffixLengthStopsAtTheGivenLimit(): void
    {
        $generalization = new TextGeneralization(Origin::Loop);
        self::assertSame(2, $generalization->commonSuffixLength(['x', 'b', 'c'], ['y', 'b', 'c'], 3));
        self::assertSame(1, $generalization->commonSuffixLength(['x', 'b', 'c'], ['y', 'b', 'c'], 1));
    }

    public function testSameAtomComparesGapsByOrigin(): void
    {
        $generalization = new TextGeneralization(Origin::Loop);
        $left = new TextHole(Origin::Loop, TypeShape::unknown());
        $right = new TextHole(Origin::Loop, TypeShape::of(['string']));
        $other = new TextHole(Origin::External, TypeShape::unknown());
        self::assertTrue($generalization->sameAtom($left, $right));
        self::assertFalse($generalization->sameAtom($left, $other));
        self::assertFalse($generalization->sameAtom($left, 'a'));
        self::assertTrue($generalization->sameAtom('a', 'a'));
    }

    public function testRebuildTurnsAtomsBackIntoSegments(): void
    {
        $segments = (new TextGeneralization(Origin::Loop))->rebuild(['a', 'b']);
        self::assertSame('ab', TextPattern::fromSegments($segments)->display());
    }

    public function testGapTypeIsAStringOnlyWhenBothSidesResolved(): void
    {
        $generalization = new TextGeneralization(Origin::Loop);
        $exact = TextPattern::fromText('a');
        $inexact = TextPattern::fromHole(new TextHole(Origin::Loop, TypeShape::unknown()));
        self::assertSame('string', $generalization->gapType($exact, $exact)->display());
        self::assertSame('mixed', $generalization->gapType($exact, $inexact)->display());
    }
}
