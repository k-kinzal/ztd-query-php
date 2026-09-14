<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertNumber;

#[CoversClass(UpsertNumber::class)]
#[UsesClass(UnsupportedSqlException::class)]
final class UpsertNumberTest extends TestCase
{
    public function testOfKeepsANumberAsItIs(): void
    {
        self::assertSame(7, (new UpsertNumber())->of(7));
        self::assertSame(1.5, (new UpsertNumber())->of(1.5));
    }

    public function testOfReadsWholeTextAsAnIntegerRatherThanAFloat(): void
    {
        self::assertSame(12, (new UpsertNumber())->of('12'));
    }

    public function testOfReadsTextWrittenTheWayAFloatIsAsAFloat(): void
    {
        self::assertSame(1.5, (new UpsertNumber())->of('1.5'));
        self::assertSame(1000.0, (new UpsertNumber())->of('1e3'));
    }

    public function testOfRefusesTextThatIsNotWrittenAsANumber(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new UpsertNumber())->of('paid');
    }

    public function testOfRefusesABooleanBecauseArithmeticOnItIsNotSomethingSqlAgreesOn(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new UpsertNumber())->of(true);
    }

    public function testIsNumericAnswersForTheValuesArithmeticCanBeDoneOn(): void
    {
        self::assertTrue((new UpsertNumber())->isNumeric(7));
        self::assertTrue((new UpsertNumber())->isNumeric(1.5));
        self::assertTrue((new UpsertNumber())->isNumeric('12'));
        self::assertFalse((new UpsertNumber())->isNumeric('paid'));
        self::assertFalse((new UpsertNumber())->isNumeric(null));
    }

    public function testPositiveKeepsTheNumberAndItsSign(): void
    {
        self::assertSame(-3, (new UpsertNumber())->positive('-3'));
    }

    public function testPositiveIsUnknownForAnUnknownValue(): void
    {
        self::assertNull((new UpsertNumber())->positive(null));
    }

    public function testNegativeTurnsTheSignAround(): void
    {
        self::assertSame(-3, (new UpsertNumber())->negative(3));
        self::assertSame(3, (new UpsertNumber())->negative(-3));
    }

    public function testNegativeIsUnknownForAnUnknownValue(): void
    {
        self::assertNull((new UpsertNumber())->negative(null));
    }

    public function testAddSumsBothSides(): void
    {
        self::assertSame(5, (new UpsertNumber())->add(2, '3'));
    }

    public function testAddIsUnknownWhenEitherSideIs(): void
    {
        self::assertNull((new UpsertNumber())->add(null, 3));
        self::assertNull((new UpsertNumber())->add(3, null));
    }

    public function testSubtractTakesTheRightSideFromTheLeft(): void
    {
        self::assertSame(2, (new UpsertNumber())->subtract(5, 3));
    }

    public function testSubtractIsUnknownWhenEitherSideIs(): void
    {
        self::assertNull((new UpsertNumber())->subtract(null, 3));
        self::assertNull((new UpsertNumber())->subtract(3, null));
    }

    public function testMultiplyMultipliesBothSides(): void
    {
        self::assertSame(6, (new UpsertNumber())->multiply(2, 3));
    }

    public function testMultiplyIsUnknownWhenEitherSideIs(): void
    {
        self::assertNull((new UpsertNumber())->multiply(null, 3));
        self::assertNull((new UpsertNumber())->multiply(3, null));
    }

    public function testDivideDividesTheLeftSideByTheRight(): void
    {
        self::assertSame(2, (new UpsertNumber())->divide(6, 3));
    }

    public function testDivideIsUnknownWhenEitherSideIs(): void
    {
        self::assertNull((new UpsertNumber())->divide(null, 3));
        self::assertNull((new UpsertNumber())->divide(3, null));
    }

    public function testDivideRefusesAZeroDivisor(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new UpsertNumber())->divide(6, 0);
    }

    public function testDivideRefusesAZeroDivisorWrittenAsAFloat(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new UpsertNumber())->divide(6, 0.0);
    }

    public function testModuloAnswersWhatIsLeftOver(): void
    {
        self::assertSame(1, (new UpsertNumber())->modulo(7, 3));
    }

    public function testModuloTakesBothSidesAsWholeNumbersFirst(): void
    {
        self::assertSame(1, (new UpsertNumber())->modulo(7.9, 3.9));
    }

    public function testModuloIsUnknownWhenEitherSideIs(): void
    {
        self::assertNull((new UpsertNumber())->modulo(null, 3));
        self::assertNull((new UpsertNumber())->modulo(3, null));
    }

    public function testModuloRefusesADivisorThatIsZeroOnceItIsAWholeNumber(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new UpsertNumber())->modulo(7, 0.5);
    }
}
