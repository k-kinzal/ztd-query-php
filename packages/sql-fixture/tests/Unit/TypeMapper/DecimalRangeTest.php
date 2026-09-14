<?php

declare(strict_types=1);

namespace Tests\Unit\TypeMapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\DecimalRange;

#[CoversClass(DecimalRange::class)]
#[UsesClass(ColumnDefinition::class)]
final class DecimalRangeTest extends TestCase
{
    #[DataProvider('providerDecimalBounds')]
    public function testPrecisionAndScaleDefineRepresentableValues(?int $precision, ?int $scale, bool $unsigned, float $minimum, float $maximum, int $digits): void
    {
        $column = new ColumnDefinition('amount', 'DECIMAL', precision: $precision, scale: $scale);
        $range = new DecimalRange($column, $unsigned);
        self::assertSame($digits, $range->scale);
        self::assertSame($minimum, $range->minimum);
        self::assertSame($maximum, $range->maximum);
    }

    public function testDefaultSignednessRetainsNegativeFractions(): void
    {
        $range = new DecimalRange(new ColumnDefinition('ratio', 'DECIMAL', precision: 2, scale: 2));
        self::assertSame(-0.99, $range->minimum);
        self::assertSame(0.99, $range->maximum);
    }

    /**
     * @return list<array{?int, ?int, bool, float, float, int}>
     */
    public static function providerDecimalBounds(): array
    {
        return [
            [null, null, false, -9999999999.0, 9999999999.0, 0],
            [null, 2, false, -99999999.99, 99999999.99, 2],
            [5, null, false, -99999.0, 99999.0, 0],
            [5, 2, false, -999.99, 999.99, 2],
            [5, 2, true, 0.0, 999.99, 2],
            [2, 2, false, -0.99, 0.99, 2],
            [2, 2, true, 0.0, 0.99, 2],
            [1, 0, false, -9.0, 9.0, 0],
            [1, 0, true, 0.0, 9.0, 0],
        ];
    }
}
