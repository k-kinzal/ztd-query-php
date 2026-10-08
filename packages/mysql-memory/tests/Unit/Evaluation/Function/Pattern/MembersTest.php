<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Evaluation\Function\Pattern\Members;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Members::class)]
#[Small]
final class MembersTest extends TestCase
{
    public function testCharacterWritesOneCharacter(): void
    {
        self::assertSame(['[a]', '[\x{E9}]'], [Members::character('a')->source(), Members::character('é')->source()]);
    }

    public function testRangeWritesTheCharactersBetweenTwo(): void
    {
        self::assertSame('[a-\x{7E}]', Members::range('a', '~')->source());
    }

    public function testEscapeWritesALetterOrDigitAsItselfAndAnyOtherCharacterByCodePoint(): void
    {
        self::assertSame(['z', '7', '\x{2E}', '\x{1F600}'], [Members::escape('z'), Members::escape('7'), Members::escape('.'), Members::escape('😀')]);
    }

    public function testUnionMergesPlainSetsAndAlternatesTheOthers(): void
    {
        self::assertSame(['[ab]', '(?:[a]|[^b])'], [Members::union([Members::character('a'), Members::character('b')])->source(), Members::union([Members::character('a'), Members::character('b')->complement()])->source()]);
    }

    public function testComplementNegatesTheSet(): void
    {
        self::assertSame(['[^a]', '(?:(?!(?:(?=[a])[b]))(?s:.))', '(?s:.)'], [Members::character('a')->complement()->source(), Members::character('a')->intersect(Members::character('b'))->complement()->source(), (new Members([]))->complement()->source()]);
    }

    public function testIntersectMatchesACharacterOfBothSets(): void
    {
        self::assertSame(1, preg_match('/\A' . Members::range('a', 'z')->intersect(Members::character('q'))->source() . '\z/u', 'q'));
    }

    public function testMinusLeavesOutTheOtherSet(): void
    {
        $source = '/\A' . Members::range('a', 'c')->minus(Members::character('b'))->source() . '\z/u';

        self::assertSame([1, 0], [preg_match($source, 'a'), preg_match($source, 'b')]);
    }

    public function testSourceOfAnEmptySetMatchesNothing(): void
    {
        self::assertSame('(?!)', (new Members([]))->source());
    }
}
