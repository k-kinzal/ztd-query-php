<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Row;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Row\RowMatch;

#[CoversClass(RowMatch::class)]
final class RowMatchTest extends TestCase
{
    public function testValuesOfReadsTheColumnsInTheOrderTheyWereAskedFor(): void
    {
        self::assertSame([2, 1], (new RowMatch())->valuesOf(['a' => 1, 'b' => 2], ['b', 'a']));
    }

    public function testValuesOfAnswersNothingWhenTheRowLacksAColumn(): void
    {
        self::assertNull((new RowMatch())->valuesOf(['a' => 1], ['a', 'b']));
    }

    public function testValuesOfKeepsANullTheRowActuallyCarries(): void
    {
        self::assertSame([null], (new RowMatch())->valuesOf(['a' => null], ['a']));
    }

    public function testCarriesIsTrueOnlyWhenEveryColumnHoldsTheValuePairedWithIt(): void
    {
        self::assertTrue((new RowMatch())->carries(['a' => 1, 'b' => 2], ['a', 'b'], [1, 2]));
        self::assertFalse((new RowMatch())->carries(['a' => 1, 'b' => 2], ['a', 'b'], [1, 3]));
    }

    public function testCarriesTellsAStringApartFromTheNumberItLooksLike(): void
    {
        self::assertFalse((new RowMatch())->carries(['a' => '1'], ['a'], [1]));
    }

    public function testCarriesIsFalseWhenTheRowLacksAColumn(): void
    {
        self::assertFalse((new RowMatch())->carries(['a' => 1], ['b'], [1]));
    }

    public function testAgreeOnComparesOnlyTheNamedColumns(): void
    {
        self::assertTrue((new RowMatch())->agreeOn(['id' => 1, 'name' => 'a'], ['id' => 1, 'name' => 'b'], ['id']));
    }

    public function testAgreeOnIsFalseWhenEitherRowLacksAKey(): void
    {
        self::assertFalse((new RowMatch())->agreeOn(['id' => 1], ['name' => 'b'], ['id']));
        self::assertFalse((new RowMatch())->agreeOn(['name' => 'b'], ['id' => 1], ['id']));
    }

    public function testAgreeOnIsTrueForAnyTwoRowsWhenNoKeyIsNamed(): void
    {
        self::assertTrue((new RowMatch())->agreeOn(['id' => 1], ['id' => 2], []));
    }

    public function testPositionOfSameKeyAnswersWhereTheKeyMatches(): void
    {
        $rows = [['id' => 1], ['id' => 2], ['id' => 3]];

        self::assertSame(1, (new RowMatch())->positionOfSameKey($rows, ['id' => 2], ['id'], []));
    }

    public function testPositionOfSameKeySkipsPositionsAlreadyPairedOff(): void
    {
        $rows = [['id' => 1], ['id' => 1]];

        self::assertSame(1, (new RowMatch())->positionOfSameKey($rows, ['id' => 1], ['id'], [0]));
    }

    public function testPositionOfSameKeyIsNothingWhenNoRowIsLeftToMatch(): void
    {
        self::assertNull((new RowMatch())->positionOfSameKey([['id' => 1]], ['id' => 1], ['id'], [0]));
    }

    public function testPositionOfIdenticalNeedsTheWholeRowToMatch(): void
    {
        $rows = [['id' => 1, 'name' => 'a'], ['id' => 1, 'name' => 'b']];

        self::assertSame(1, (new RowMatch())->positionOfIdentical($rows, ['id' => 1, 'name' => 'b'], []));
    }

    public function testPositionOfIdenticalSkipsPositionsAlreadyPairedOff(): void
    {
        $rows = [['id' => 1], ['id' => 1]];

        self::assertSame(1, (new RowMatch())->positionOfIdentical($rows, ['id' => 1], [0]));
    }

    public function testPositionOfIdenticalIsNothingWhenNoRowIsLeftToMatch(): void
    {
        self::assertNull((new RowMatch())->positionOfIdentical([['id' => 2]], ['id' => 1], []));
    }
}
