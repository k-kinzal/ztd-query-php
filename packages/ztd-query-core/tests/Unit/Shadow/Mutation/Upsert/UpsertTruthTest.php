<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertNumber;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertTruth;

#[CoversClass(UpsertTruth::class)]
#[UsesClass(UpsertNumber::class)]
#[UsesClass(UnsupportedSqlException::class)]
final class UpsertTruthTest extends TestCase
{
    public function testOfKeepsABooleanAsItIs(): void
    {
        self::assertTrue((new UpsertTruth())->of(true));
        self::assertFalse((new UpsertTruth())->of(false));
    }

    public function testOfCountsAnyNumberButZeroAsTrue(): void
    {
        self::assertTrue((new UpsertTruth())->of(1));
        self::assertTrue((new UpsertTruth())->of(-1));
        self::assertFalse((new UpsertTruth())->of(0));
        self::assertFalse((new UpsertTruth())->of(0.0));
    }

    public function testOfIsUnknownForAnUnknownValue(): void
    {
        self::assertNull((new UpsertTruth())->of(null));
    }

    public function testOfRefusesAValueThatIsNeitherBooleanNorANumber(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new UpsertTruth())->of('paid');
    }

    public function testNotTurnsTheAnswerAround(): void
    {
        self::assertFalse((new UpsertTruth())->not(1));
        self::assertTrue((new UpsertTruth())->not(0));
    }

    public function testNotLeavesTheUnknownUnknown(): void
    {
        self::assertNull((new UpsertTruth())->not(null));
    }

    public function testAndHoldsOnlyWhenBothSidesDo(): void
    {
        self::assertTrue((new UpsertTruth())->and(true, true));
        self::assertFalse((new UpsertTruth())->and(true, false));
    }

    public function testAndIsFalseWhereOneSideIsFalseHoweverUnknownTheOtherIs(): void
    {
        self::assertFalse((new UpsertTruth())->and(null, false));
        self::assertFalse((new UpsertTruth())->and(false, null));
    }

    public function testAndIsUnknownWhereOneSideIsUnknownAndTheOtherHolds(): void
    {
        self::assertNull((new UpsertTruth())->and(true, null));
        self::assertNull((new UpsertTruth())->and(null, true));
    }

    public function testOrHoldsWhenEitherSideDoes(): void
    {
        self::assertTrue((new UpsertTruth())->or(false, true));
        self::assertFalse((new UpsertTruth())->or(false, false));
    }

    public function testOrIsTrueWhereOneSideHoldsHoweverUnknownTheOtherIs(): void
    {
        self::assertTrue((new UpsertTruth())->or(null, true));
        self::assertTrue((new UpsertTruth())->or(true, null));
    }

    public function testOrIsUnknownWhereOneSideIsUnknownAndTheOtherDoesNotHold(): void
    {
        self::assertNull((new UpsertTruth())->or(false, null));
        self::assertNull((new UpsertTruth())->or(null, false));
    }
}
