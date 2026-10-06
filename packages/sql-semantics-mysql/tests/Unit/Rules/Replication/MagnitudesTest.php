<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Replication\Magnitudes;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

#[CoversClass(Magnitudes::class)]
#[Small]
final class MagnitudesTest extends TestCase
{
    public function testIntegerReadsTheIntegerPrefixAndHexadecimalDigits(): void
    {
        self::assertSame(7, (new Magnitudes())->integer(new Numeral('007.9')));
        self::assertSame(255, (new Magnitudes())->integer(new Numeral('ff', true)));
        self::assertNull((new Magnitudes())->integer(new Numeral('99999999999999999999')));
    }

    public function testExceedsTreatsAnOverflowAsBeyondTheBound(): void
    {
        self::assertTrue((new Magnitudes())->exceeds(new Numeral('99999999999999999999'), 10));
        self::assertFalse((new Magnitudes())->exceeds(new Numeral('10'), 10));
    }

    public function testFlagAcceptsZeroAndOne(): void
    {
        self::assertTrue((new Magnitudes())->flag(new Numeral('01')));
        self::assertFalse((new Magnitudes())->flag(new Numeral('2')));
    }

    public function testFractionalRecognisesDecimalAndFloatingNumbers(): void
    {
        self::assertTrue((new Magnitudes())->fractional(new Numeral('1e3')));
        self::assertFalse((new Magnitudes())->fractional(new Numeral('1')));
        self::assertFalse((new Magnitudes())->fractional(new Numeral('1e', true)));
    }
}
