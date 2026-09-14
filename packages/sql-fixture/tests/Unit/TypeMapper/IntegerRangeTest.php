<?php

declare(strict_types=1);

namespace Tests\Unit\TypeMapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\TypeMapper\IntegerRange;
use SqlFixture\TypeMapper\IntegerWidth;

#[CoversClass(IntegerRange::class)]
#[UsesClass(IntegerWidth::class)]
final class IntegerRangeTest extends TestCase
{
    #[DataProvider('providerSqlIntegerBounds')]
    public function testWidthAndSignednessDefineTheDomain(IntegerWidth $width, bool $unsigned, int $minimum, int $maximum): void
    {
        $range = new IntegerRange($width, $unsigned);
        self::assertSame($minimum, $range->minimum);
        self::assertSame($maximum, $range->maximum);
    }

    public function testDefaultSignednessIncludesNegativeValues(): void
    {
        $range = new IntegerRange(IntegerWidth::Bits8);
        self::assertSame(-128, $range->minimum);
        self::assertSame(127, $range->maximum);
    }

    /**
     * @return list<array{IntegerWidth, bool, int, int}>
     */
    public static function providerSqlIntegerBounds(): array
    {
        return [
            [IntegerWidth::Bits8, false, -128, 127],
            [IntegerWidth::Bits8, true, 0, 255],
            [IntegerWidth::Bits16, false, -32768, 32767],
            [IntegerWidth::Bits16, true, 0, 65535],
            [IntegerWidth::Bits24, false, -8388608, 8388607],
            [IntegerWidth::Bits24, true, 0, 16777215],
            [IntegerWidth::Bits32, false, -2147483648, 2147483647],
            [IntegerWidth::Bits32, true, 0, 4294967295],
            [IntegerWidth::Bits64, false, PHP_INT_MIN, PHP_INT_MAX],
            [IntegerWidth::Bits64, true, 0, PHP_INT_MAX],
        ];
    }
}
