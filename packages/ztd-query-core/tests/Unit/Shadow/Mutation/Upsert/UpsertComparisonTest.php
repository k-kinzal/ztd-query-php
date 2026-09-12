<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertComparison;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertNumber;

#[CoversClass(UpsertComparison::class)]
#[UsesClass(UpsertNumber::class)]
#[UsesClass(UnsupportedSqlException::class)]
final class UpsertComparisonTest extends TestCase
{
    public function testOfOrdersTwoNumbersAsNumbers(): void
    {
        self::assertSame(-1, (new UpsertComparison())->of(9, 10));
    }

    public function testOfOrdersNumbersWrittenAsTextAsNumbersToo(): void
    {
        self::assertSame(-1, (new UpsertComparison())->of('9', '10'));
    }

    public function testOfOrdersTwoPiecesOfTextAsText(): void
    {
        self::assertSame(1, (new UpsertComparison())->of('b', 'a'));
    }

    public function testOfIsUnknownWhenEitherSideIs(): void
    {
        self::assertNull((new UpsertComparison())->of(null, 1));
        self::assertNull((new UpsertComparison())->of(1, null));
    }

    public function testOfRefusesANumberAgainstText(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new UpsertComparison())->of(1, 'paid');
    }

    public function testOfRefusesTextAgainstANumber(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new UpsertComparison())->of('paid', 1);
    }

    public function testTextKeepsTextAsItIs(): void
    {
        self::assertSame('paid', (new UpsertComparison())->text('paid'));
    }

    public function testTextWritesABooleanTheWayADatabaseComparesIt(): void
    {
        self::assertSame('1', (new UpsertComparison())->text(true));
        self::assertSame('', (new UpsertComparison())->text(false));
    }

    public function testTextRefusesAValueWithNoTextForm(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new UpsertComparison())->text(null);
    }

    public function testEqualAnswersWhetherTheTwoAreTheSame(): void
    {
        self::assertTrue((new UpsertComparison())->equal(1, '1'));
        self::assertFalse((new UpsertComparison())->equal(1, 2));
        self::assertNull((new UpsertComparison())->equal(null, 1));
    }

    public function testNotEqualAnswersWhetherTheTwoDiffer(): void
    {
        self::assertTrue((new UpsertComparison())->notEqual(1, 2));
        self::assertFalse((new UpsertComparison())->notEqual(1, 1));
        self::assertNull((new UpsertComparison())->notEqual(null, 1));
    }

    public function testLessAnswersWhetherTheLeftOrdersFirst(): void
    {
        self::assertTrue((new UpsertComparison())->less(1, 2));
        self::assertFalse((new UpsertComparison())->less(2, 2));
        self::assertNull((new UpsertComparison())->less(null, 1));
    }

    public function testLessOrEqualAlsoHoldsWhereTheTwoOrderTogether(): void
    {
        self::assertTrue((new UpsertComparison())->lessOrEqual(2, 2));
        self::assertFalse((new UpsertComparison())->lessOrEqual(3, 2));
        self::assertNull((new UpsertComparison())->lessOrEqual(null, 1));
    }

    public function testGreaterAnswersWhetherTheLeftOrdersLast(): void
    {
        self::assertTrue((new UpsertComparison())->greater(3, 2));
        self::assertFalse((new UpsertComparison())->greater(2, 2));
        self::assertNull((new UpsertComparison())->greater(null, 1));
    }

    public function testGreaterOrEqualAlsoHoldsWhereTheTwoOrderTogether(): void
    {
        self::assertTrue((new UpsertComparison())->greaterOrEqual(2, 2));
        self::assertFalse((new UpsertComparison())->greaterOrEqual(1, 2));
        self::assertNull((new UpsertComparison())->greaterOrEqual(null, 1));
    }
}
