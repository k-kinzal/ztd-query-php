<?php

declare(strict_types=1);

namespace Tests\Unit\Generation\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Plan\LexemeConstraint;
use SqlFaker\Generation\Value\IntegerDomain;

#[CoversClass(LexemeConstraint::class)]
#[UsesClass(IntegerDomain::class)]
final class LexemeConstraintTest extends TestCase
{
    public function testOneOfChoicesRemainReachableAndIntersectionRemovesOnlyForbiddenSpellings(): void
    {
        $names = LexemeConstraint::oneOf('users', 'orders', 'users');
        self::assertSame('users', $names->choose(static fn (int $count): int => 0));
        self::assertSame('orders', $names->choose(static fn (int $count): int => $count - 1));
        $intersection = $names->intersect(LexemeConstraint::oneOf('orders', 'products'));
        self::assertSame('orders', $intersection->choose(static fn (int $count): int => 0));
        self::assertFalse($intersection->accepts('users'));
        self::assertTrue($names->accepts('users'));
    }

    public function testIntegersBoundsAndFiniteSetsIntersectWithoutSampling(): void
    {
        $range = LexemeConstraint::integers(10, 99)->intersect(LexemeConstraint::integers(20, 30));
        self::assertSame('20', $range->choose(static fn (int $count): int => 0));
        self::assertSame('30', $range->choose(static fn (int $count): int => $count - 1));
        self::assertFalse($range->accepts('31'));
        self::assertFalse($range->accepts("'20'"));
        $finite = $range->intersect(LexemeConstraint::oneOf('19', '20', '31'));
        self::assertSame('20', $finite->choose(static fn (int $count): int => 0));
    }


    public function testChooseRetainsTheWholeIntegerIntervalIncludingItsBounds(): void
    {
        $range = LexemeConstraint::integers(0, PHP_INT_MAX);
        self::assertSame('0', $range->choose(static fn (int $count): int => 0));
        self::assertSame((string) PHP_INT_MAX, $range->choose(static fn (int $count): int => $count - 1));
    }

    public function testAcceptsUsesLexicalSpellingsRatherThanNumericCoercion(): void
    {
        $range = LexemeConstraint::integers(10, 20);
        self::assertTrue($range->accepts('10'));
        self::assertTrue($range->accepts('20'));
        self::assertFalse($range->accepts('9'));
        self::assertFalse($range->accepts('21'));
        self::assertFalse($range->accepts('-10'));
        self::assertFalse($range->accepts('+10'));
        self::assertFalse($range->accepts("'10'"));
        self::assertFalse($range->accepts('1e1'));
        self::assertFalse($range->accepts('10.0'));
        self::assertFalse($range->accepts(' 10'));

    }

    public function testIntersectIsIndependentOfWhichSideHoldsTheFiniteSet(): void
    {
        $range = LexemeConstraint::integers(10, 20);
        $set = LexemeConstraint::oneOf('9', '10', '15', '21');
        self::assertEquals($range->intersect($set), $set->intersect($range));
        self::assertFalse($range->intersect($set)->accepts('11'));
        self::assertTrue($range->intersect($set)->accepts('15'));
    }


    public function testOneOfDeduplicatesValuesWithoutLeavingChoiceIndexGaps(): void
    {
        $names = LexemeConstraint::oneOf('users', 'users', 'orders');
        self::assertSame('orders', $names->choose(static fn (int $count): int => $count - 1));
    }

    public function testIntegersSupportsSingletonIntervalsIncludingZero(): void
    {
        $zero = LexemeConstraint::integers(0, 0);
        self::assertSame('0', $zero->choose(static fn (int $count): int => $count - 1));
        self::assertTrue($zero->accepts('0'));
        self::assertSame('42', LexemeConstraint::integers(42, 42)->choose(static fn (int $count): int => 0));
    }

    public function testChooseDefaultIntegerDomainIncludesZero(): void
    {
        $domain = new LexemeConstraint([]);
        self::assertSame('0', $domain->choose(static fn (int $count): int => 0));
        self::assertSame((string) PHP_INT_MAX, $domain->choose(static fn (int $count): int => $count - 1));
    }

}
