<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use MySqlMemory\Value\NumericText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(NumericText::class)]
#[Small]
final class NumericTextTest extends TestCase
{
    public function testRealReadsTheLeadingFloatingPointNumber(): void
    {
        $read = NumericText::real('  1.5e3xyz');

        self::assertSame(['1.5e3', false], [$read->number, $read->complete]);
    }

    public function testRealIsCompleteWhenOnlySpacesFollow(): void
    {
        $spaces = NumericText::real('12 ');
        $empty = NumericText::real('');

        self::assertSame([['12', true], ['0', true]], [[$spaces->number, $spaces->complete], [$empty->number, $empty->complete]]);
    }

    public function testRealReadsZeroForATextWithoutANumber(): void
    {
        $read = NumericText::real('abc');

        self::assertSame(['0', false], [$read->number, $read->complete]);
    }

    public function testExactAppliesTheExponent(): void
    {
        $large = NumericText::exact('1.5e3');
        $small = NumericText::exact('12.50e-1');

        self::assertSame([['1500', true], ['1.250', true]], [[$large->number, $large->complete], [$small->number, $small->complete]]);
    }

    public function testExactWritesTheNumberCanonically(): void
    {
        $point = NumericText::exact('7.');
        $zeros = NumericText::exact('-0012x');

        self::assertSame([['7', true], ['-12', false]], [[$point->number, $point->complete], [$zeros->number, $zeros->complete]]);
    }

    public function testIntegerStopsAtThePoint(): void
    {
        $read = NumericText::integer('  -12.9');

        self::assertSame(['-12', false], [$read->number, $read->complete]);
    }

    public function testIntegerReadsASignedNumber(): void
    {
        $signed = NumericText::integer('+007 ');
        $empty = NumericText::integer('');
        $none = NumericText::integer('x1');

        self::assertSame([['7', true], ['0', true], ['0', false]], [[$signed->number, $signed->complete], [$empty->number, $empty->complete], [$none->number, $none->complete]]);
    }
}
